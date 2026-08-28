import logging
from datetime import timedelta

from django.db.models import Sum, Count, Q
from django.utils import timezone
from rest_framework import generics, status
from rest_framework.response import Response
from rest_framework.views import APIView

from .models import User, TransactionEvent, DailyAggregate, SystemAlert
from .serializers import (
    UserSerializer, TransactionEventSerializer, DailyAggregateSerializer,
    SystemAlertSerializer, RevenueBreakdownSerializer, UserGrowthSerializer,
)
from .permissions import IsServiceAccount, IsAdminUser

logger = logging.getLogger('analytics')


class DashboardView(APIView):
    """Return today's aggregates, 7-day trends, and quick stats."""

    def get(self, request):
        today = timezone.now().date()
        seven_days_ago = today - timedelta(days=6)

        today_agg = DailyAggregate.objects.filter(date=today).first()
        if not today_agg:
            today_agg = DailyAggregate(date=today)

        weekly_trend = DailyAggregate.objects.filter(
            date__gte=seven_days_ago, date__lte=today
        ).order_by('date')

        totals = TransactionEvent.objects.aggregate(
            total_users=Count('user_id', distinct=True),
            total_revenue=Sum('amount'),
            total_fees=Sum('fee'),
            total_transactions=Count('id'),
        )

        category_breakdown = (
            TransactionEvent.objects.filter(
                status='successful',
                created_at__date__gte=seven_days_ago,
            )
            .values('category')
            .annotate(
                volume=Sum('amount'),
                count=Count('id'),
            )
            .order_by('-volume')
        )
        category_data = {item['category']: {'volume': str(item['volume']), 'count': item['count']} for item in category_breakdown}

        recent_alerts = SystemAlert.objects.filter(is_resolved=False)[:5]

        return Response({
            'today': DailyAggregateSerializer(today_agg).data,
            'totals': {
                'total_users': totals['total_users'] or 0,
                'total_revenue': str(totals['total_revenue'] or 0),
                'total_fees': str(totals['total_fees'] or 0),
                'total_transactions': totals['total_transactions'] or 0,
            },
            'weekly_trend': DailyAggregateSerializer(weekly_trend, many=True).data,
            'category_breakdown': category_data,
            'recent_alerts': SystemAlertSerializer(recent_alerts, many=True).data,
        })


class TransactionAnalyticsView(APIView):
    """Filter by date range, category, status. Return daily aggregates + totals."""

    def get(self, request):
        start_date = request.query_params.get('start_date')
        end_date = request.query_params.get('end_date')
        category = request.query_params.get('category')
        tx_status = request.query_params.get('status')

        qs = DailyAggregate.objects.all()

        if start_date:
            qs = qs.filter(date__gte=start_date)
        if end_date:
            qs = qs.filter(date__lte=end_date)

        agg_qs = TransactionEvent.objects.all()
        if start_date:
            agg_qs = agg_qs.filter(created_at__date__gte=start_date)
        if end_date:
            agg_qs = agg_qs.filter(created_at__date__lte=end_date)
        if category:
            agg_qs = agg_qs.filter(category=category)
        if tx_status:
            agg_qs = agg_qs.filter(status=tx_status)

        totals = agg_qs.aggregate(
            total_count=Count('id'),
            successful_count=Count('id', filter=Q(status='successful')),
            failed_count=Count('id', filter=Q(status='failed')),
            total_amount=Sum('amount'),
            total_fees=Sum('fee'),
        )

        return Response({
            'daily_aggregates': DailyAggregateSerializer(qs, many=True).data,
            'totals': {
                'total_count': totals['total_count'] or 0,
                'successful_count': totals['successful_count'] or 0,
                'failed_count': totals['failed_count'] or 0,
                'total_amount': str(totals['total_amount'] or 0),
                'total_fees': str(totals['total_fees'] or 0),
            },
        })


class RevenueAnalyticsView(APIView):
    """Revenue breakdown by category, by day/week/month."""

    def get(self, request):
        start_date = request.query_params.get('start_date')
        end_date = request.query_params.get('end_date')
        group_by = request.query_params.get('group_by', 'category')

        qs = TransactionEvent.objects.filter(status='successful')

        if start_date:
            qs = qs.filter(created_at__date__gte=start_date)
        if end_date:
            qs = qs.filter(created_at__date__lte=end_date)

        breakdown = (
            qs.values('category')
            .annotate(
                volume=Sum('amount'),
                fees=Sum('fee'),
                count=Count('id'),
            )
            .order_by('-volume')
        )

        total_volume = sum(item['volume'] for item in breakdown)
        breakdown_data = []
        for item in breakdown:
            percentage = round((item['volume'] / total_volume * 100), 2) if total_volume > 0 else 0
            breakdown_data.append(RevenueBreakdownSerializer({
                'category': item['category'],
                'volume': item['volume'],
                'fees': item['fees'],
                'count': item['count'],
                'percentage': percentage,
            }).data)

        daily_revenue = []
        if group_by in ('day', 'week', 'month'):
            from django.db.models.functions import TruncDate, TruncWeek, TruncMonth
            trunc_map = {
                'day': TruncDate('created_at'),
                'week': TruncWeek('created_at'),
                'month': TruncMonth('created_at'),
            }
            daily_revenue = list(
                qs.annotate(period=trunc_map[group_by])
                .values('period')
                .annotate(volume=Sum('amount'), fees=Sum('fee'), count=Count('id'))
                .order_by('period')
            )
            daily_revenue = [
                {
                    'period': item['period'].isoformat() if item['period'] else None,
                    'volume': str(item['volume']),
                    'fees': str(item['fees']),
                    'count': item['count'],
                }
                for item in daily_revenue
            ]

        return Response({
            'breakdown': breakdown_data,
            'time_series': daily_revenue,
            'total_revenue': str(total_volume),
        })


class UserAnalyticsView(APIView):
    """User growth over time, active users, retention metrics."""

    def get(self, request):
        start_date = request.query_params.get('start_date')
        end_date = request.query_params.get('end_date')

        if not start_date:
            start_date = (timezone.now() - timedelta(days=30)).date()
        if not end_date:
            end_date = timezone.now().date()

        users = User.objects.filter(created_at__date__gte=start_date, created_at__date__lte=end_date)
        total_all = User.objects.count()

        from django.db.models.functions import TruncDate
        daily_new = (
            users
            .annotate(date=TruncDate('created_at'))
            .values('date')
            .annotate(new_users=Count('id'))
            .order_by('date')
        )

        growth_data = []
        running_total = User.objects.filter(created_at__date__lt=start_date).count()
        for entry in daily_new:
            running_total += entry['new_users']
            active_users = TransactionEvent.objects.filter(
                created_at__date=entry['date']
            ).values('user_id').distinct().count()
            growth_data.append(UserGrowthSerializer({
                'date': entry['date'],
                'new_users': entry['new_users'],
                'total_users': running_total,
                'active_users': active_users,
            }).data)

        recent_cutoff = timezone.now() - timedelta(days=30)
        active_last_30 = TransactionEvent.objects.filter(
            created_at__gte=recent_cutoff
        ).values('user_id').distinct().count()

        return Response({
            'growth': growth_data,
            'summary': {
                'total_users': total_all,
                'active_last_30_days': active_last_30,
                'retention_rate': round((active_last_30 / total_all * 100), 2) if total_all > 0 else 0,
            },
        })


class TransactionEventListView(generics.ListAPIView):
    """Paginated, filterable list of all transaction events."""
    serializer_class = TransactionEventSerializer
    filterset_fields = ['type', 'category', 'status', 'provider', 'user_id']
    search_fields = ['transaction_id', 'reference', 'user_id']
    ordering_fields = ['amount', 'created_at', 'fee']

    def get_queryset(self):
        qs = TransactionEvent.objects.all()
        start_date = self.request.query_params.get('start_date')
        end_date = self.request.query_params.get('end_date')
        if start_date:
            qs = qs.filter(created_at__date__gte=start_date)
        if end_date:
            qs = qs.filter(created_at__date__lte=end_date)
        return qs


class TransactionEventDetailView(generics.RetrieveAPIView):
    """Single transaction event detail."""
    queryset = TransactionEvent.objects.all()
    serializer_class = TransactionEventSerializer
    lookup_field = 'transaction_id'


class SyncTransactionsView(APIView):
    """Receive batch transaction events from billing service (API key auth)."""
    permission_classes = [IsServiceAccount]

    def post(self, request):
        events = request.data.get('events', [])
        if not isinstance(events, list):
            return Response(
                {'error': 'events must be a list'},
                status=status.HTTP_400_BAD_REQUEST,
            )

        created = 0
        updated = 0
        errors = []

        for event_data in events:
            tx_id = event_data.get('transaction_id')
            if not tx_id:
                errors.append({'transaction_id': None, 'error': 'Missing transaction_id'})
                continue

            try:
                obj, was_created = TransactionEvent.objects.update_or_create(
                    transaction_id=tx_id,
                    defaults={
                        'user_id': event_data.get('user_id', ''),
                        'type': event_data.get('type', 'debit'),
                        'category': event_data.get('category', 'airtime'),
                        'reference': event_data.get('reference', ''),
                        'amount': event_data.get('amount', 0),
                        'fee': event_data.get('fee', 0),
                        'status': event_data.get('status', 'pending'),
                        'provider': event_data.get('provider'),
                        'metadata': event_data.get('metadata', {}),
                        'completed_at': event_data.get('completed_at'),
                    },
                )
                if was_created:
                    created += 1
                else:
                    updated += 1
            except Exception as e:
                errors.append({'transaction_id': tx_id, 'error': str(e)})

        logger.info(f"Sync complete: {created} created, {updated} updated, {len(errors)} errors")
        return Response({
            'created': created,
            'updated': updated,
            'errors': errors,
        }, status=status.HTTP_200_OK)


class SystemAlertListView(generics.ListAPIView):
    """List system alerts."""
    serializer_class = SystemAlertSerializer
    filterset_fields = ['severity', 'is_resolved']

    def get_queryset(self):
        return SystemAlert.objects.all()


class SystemAlertCreateView(generics.CreateAPIView):
    """Create alert (admin only)."""
    serializer_class = SystemAlertSerializer
    permission_classes = [IsAdminUser]
