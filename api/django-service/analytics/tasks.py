import logging
from datetime import timedelta

from celery import shared_task
from django.db.models import Sum, Count, Q
from django.utils import timezone

logger = logging.getLogger('analytics')


@shared_task(name='analytics.compute_daily_aggregate')
def compute_daily_aggregate(date_str=None):
    """Aggregate transaction data for a given date."""
    from .models import TransactionEvent, DailyAggregate, User

    if date_str:
        from datetime import date as dt_date
        parts = date_str.split('-')
        target_date = dt_date(int(parts[0]), int(parts[1]), int(parts[2]))
    else:
        target_date = (timezone.now() - timedelta(days=1)).date()

    day_start = timezone.make_aware(timezone.datetime.combine(target_date, timezone.datetime.min.time()))
    day_end = day_start + timedelta(days=1)

    transactions = TransactionEvent.objects.filter(
        created_at__gte=day_start, created_at__lt=day_end
    )

    agg = transactions.aggregate(
        total=Count('id'),
        successful=Count('id', filter=Q(status='successful')),
        failed=Count('id', filter=Q(status='failed')),
        revenue=Sum('amount', filter=Q(status='successful')),
        fees=Sum('fee', filter=Q(status='successful')),
    )

    category_volumes = transactions.filter(status='successful').values('category').annotate(
        vol=Sum('amount')
    )
    cat_map = {item['category']: item['vol'] or 0 for item in category_volumes}

    total_users = User.objects.count()
    new_users = User.objects.filter(
        created_at__gte=day_start, created_at__lt=day_end
    ).count()

    DailyAggregate.objects.update_or_create(
        date=target_date,
        defaults={
            'total_users': total_users,
            'new_users': new_users,
            'total_transactions': agg['total'] or 0,
            'successful_transactions': agg['successful'] or 0,
            'failed_transactions': agg['failed'] or 0,
            'total_revenue': agg['revenue'] or 0,
            'total_fees': agg['fees'] or 0,
            'airtime_volume': cat_map.get('airtime', 0),
            'data_volume': cat_map.get('data', 0),
            'electricity_volume': cat_map.get('electricity', 0),
            'cable_volume': cat_map.get('cable', 0),
        },
    )

    logger.info(f"Daily aggregate computed for {target_date}")
    return f"Aggregate computed for {target_date}"


@shared_task(name='analytics.sync_transactions')
def sync_transactions():
    """Periodic task to sync from billing service."""
    import requests
    from django.conf import settings

    try:
        response = requests.get(
            f"{settings.BILLING_SERVICE_URL}/api/internal/transactions/pending",
            headers={'X-Internal-API-Key': settings.INTERNAL_API_KEY},
            timeout=30,
        )
        if response.status_code == 200:
            data = response.json()
            events = data.get('transactions', [])
            if events:
                from .views import SyncTransactionsView
                from rest_framework.test import APIRequestFactory
                factory = APIRequestFactory()
                request = factory.post('/sync/', {'events': events}, format='json')
                sync_view = SyncTransactionsView.as_view()
                response = sync_view(request)
                logger.info(f"Transaction sync completed: {response.data}")
                return f"Synced {len(events)} transactions"
        logger.warning(f"Sync returned status {response.status_code}")
    except Exception as e:
        logger.error(f"Transaction sync failed: {e}")
    return "Sync attempted"


@shared_task(name='analytics.cleanup_old_events')
def cleanup_old_events():
    """Delete events older than 90 days."""
    from .models import TransactionEvent

    cutoff = timezone.now() - timedelta(days=90)
    deleted_count, _ = TransactionEvent.objects.filter(created_at__lt=cutoff).delete()
    logger.info(f"Cleaned up {deleted_count} old transaction events")
    return f"Deleted {deleted_count} events"
