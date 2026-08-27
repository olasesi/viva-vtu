import csv
import io
import logging
import os
from datetime import datetime
from decimal import Decimal

from django.conf import settings
from django.db.models import Sum, Count, Q
from django.utils import timezone

logger = logging.getLogger('reports')

REPORTS_DIR = os.path.join(settings.BASE_DIR, 'media', 'reports')


def _ensure_reports_dir():
    os.makedirs(REPORTS_DIR, exist_ok=True)


def _parse_date(date_str):
    if not date_str:
        return None
    from dateutil.parser import parse as dateparse
    return dateparse(date_str).date()


def _get_date_range(params):
    start = _parse_date(params.get('start_date'))
    end = _parse_date(params.get('end_date'))
    if not end:
        end = timezone.now().date()
    if not start:
        from datetime import timedelta
        start = end - timedelta(days=30)
    return start, end


def _generate_filename(report_type, fmt, timestamp):
    return f"{report_type}_report_{timestamp.strftime('%Y%m%d_%H%M%S')}.{fmt}"


def generate_transaction_report(params, user):
    from analytics.models import TransactionEvent

    _ensure_reports_dir()
    start, end = _get_date_range(params)
    category = params.get('category')
    tx_status = params.get('status')
    fmt = params.get('format', 'csv')

    qs = TransactionEvent.objects.filter(
        created_at__date__gte=start, created_at__date__lte=end
    )
    if category:
        qs = qs.filter(category=category)
    if tx_status:
        qs = qs.filter(status=tx_status)

    qs = qs.order_by('-created_at')

    timestamp = timezone.now()
    filename = _generate_filename('transaction', fmt, timestamp)
    filepath = os.path.join(REPORTS_DIR, filename)

    if fmt == 'csv':
        with open(filepath, 'w', newline='', encoding='utf-8') as f:
            writer = csv.writer(f)
            writer.writerow([
                'Transaction ID', 'User ID', 'Type', 'Category', 'Reference',
                'Amount', 'Fee', 'Status', 'Provider', 'Created At', 'Completed At',
            ])
            for tx in qs.iterator(chunk_size=1000):
                writer.writerow([
                    tx.transaction_id, tx.user_id, tx.get_type_display(),
                    tx.get_category_display(), tx.reference, str(tx.amount),
                    str(tx.fee), tx.get_status_display(), tx.provider or '',
                    tx.created_at.isoformat() if tx.created_at else '',
                    tx.completed_at.isoformat() if tx.completed_at else '',
                ])

    elif fmt == 'xlsx':
        import pandas as pd

        data = list(qs.values(
            'transaction_id', 'user_id', 'type', 'category', 'reference',
            'amount', 'fee', 'status', 'provider', 'created_at', 'completed_at',
        ))
        df = pd.DataFrame(data)
        if not df.empty:
            df['type'] = df['type'].map({'credit': 'Credit', 'debit': 'Debit'})
            df['category'] = df['category'].map(dict(TransactionEvent.EVENT_TYPES))
            df['status'] = df['status'].map(dict(TransactionEvent.STATUS_CHOICES))
        df.columns = [
            'Transaction ID', 'User ID', 'Type', 'Category', 'Reference',
            'Amount', 'Fee', 'Status', 'Provider', 'Created At', 'Completed At',
        ]
        df.to_excel(filepath, index=False, engine='openpyxl')

    elif fmt == 'pdf':
        from reportlab.lib import colors
        from reportlab.lib.pagesizes import A4, landscape
        from reportlab.lib.styles import getSampleStyleSheet
        from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph, Spacer

        doc = SimpleDocTemplate(filepath, pagesize=landscape(A4))
        elements = []
        styles = getSampleStyleSheet()
        elements.append(Paragraph(f"Transaction Report ({start} to {end})", styles['Title']))
        elements.append(Spacer(1, 20))

        data_rows = [[
            'TX ID', 'User ID', 'Type', 'Category', 'Amount', 'Fee', 'Status',
        ]]
        for tx in qs[:500]:
            data_rows.append([
                tx.transaction_id[:16], tx.user_id[:16],
                tx.get_type_display(), tx.get_category_display(),
                str(tx.amount), str(tx.fee), tx.get_status_display(),
            ])

        table = Table(data_rows, repeatRows=1)
        table.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (-1, 0), colors.HexColor('#1a237e')),
            ('TEXTCOLOR', (0, 0), (-1, 0), colors.white),
            ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
            ('FONTSIZE', (0, 0), (-1, -1), 8),
            ('ALIGN', (0, 0), (-1, -1), 'LEFT'),
            ('GRID', (0, 0), (-1, -1), 0.5, colors.grey),
            ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, colors.HexColor('#f5f5f5')]),
        ]))
        elements.append(table)

        summary = f"Total transactions: {qs.count()}"
        elements.append(Spacer(1, 10))
        elements.append(Paragraph(summary, styles['Normal']))
        doc.build(elements)

    file_size = os.path.getsize(filepath)
    return filepath, file_size


def generate_revenue_report(params, user):
    from analytics.models import TransactionEvent

    _ensure_reports_dir()
    start, end = _get_date_range(params)
    fmt = params.get('format', 'csv')

    qs = TransactionEvent.objects.filter(
        status='successful',
        created_at__date__gte=start,
        created_at__date__lte=end,
    )

    breakdown = (
        qs.values('category')
        .annotate(
            volume=Sum('amount'),
            fees=Sum('fee'),
            count=Count('id'),
        )
        .order_by('-volume')
    )

    timestamp = timezone.now()
    filename = _generate_filename('revenue', fmt, timestamp)
    filepath = os.path.join(REPORTS_DIR, filename)

    if fmt == 'csv':
        with open(filepath, 'w', newline='', encoding='utf-8') as f:
            writer = csv.writer(f)
            writer.writerow(['Category', 'Volume', 'Fees', 'Transaction Count'])
            for item in breakdown:
                writer.writerow([
                    item['category'], str(item['volume']),
                    str(item['fees']), item['count'],
                ])

            writer.writerow([])
            writer.writerow(['Summary'])
            total_vol = qs.aggregate(total=Sum('amount'))['total'] or 0
            total_fees = qs.aggregate(total=Sum('fee'))['total'] or 0
            total_count = qs.count()
            writer.writerow(['Total Volume', str(total_vol)])
            writer.writerow(['Total Fees', str(total_fees)])
            writer.writerow(['Total Transactions', total_count])

    elif fmt == 'xlsx':
        import pandas as pd

        data = [
            {
                'Category': item['category'],
                'Volume': float(item['volume']),
                'Fees': float(item['fees']),
                'Transaction Count': item['count'],
            }
            for item in breakdown
        ]
        df = pd.DataFrame(data)
        df.to_excel(filepath, index=False, engine='openpyxl')

    elif fmt == 'pdf':
        from reportlab.lib import colors
        from reportlab.lib.pagesizes import A4
        from reportlab.lib.styles import getSampleStyleSheet
        from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph, Spacer

        doc = SimpleDocTemplate(filepath, pagesize=A4)
        elements = []
        styles = getSampleStyleSheet()
        elements.append(Paragraph(f"Revenue Report ({start} to {end})", styles['Title']))
        elements.append(Spacer(1, 20))

        data_rows = [['Category', 'Volume', 'Fees', 'Count']]
        for item in breakdown:
            data_rows.append([
                item['category'], str(item['volume']),
                str(item['fees']), str(item['count']),
            ])

        table = Table(data_rows, repeatRows=1)
        table.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (-1, 0), colors.HexColor('#1b5e20')),
            ('TEXTCOLOR', (0, 0), (-1, 0), colors.white),
            ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
            ('FONTSIZE', (0, 0), (-1, -1), 9),
            ('GRID', (0, 0), (-1, -1), 0.5, colors.grey),
            ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, colors.HexColor('#f5f5f5')]),
        ]))
        elements.append(table)
        doc.build(elements)

    file_size = os.path.getsize(filepath)
    return filepath, file_size


def generate_user_report(params, user):
    from analytics.models import User, TransactionEvent
    from django.db.models.functions import TruncDate

    _ensure_reports_dir()
    start, end = _get_date_range(params)
    fmt = params.get('format', 'csv')

    users = User.objects.filter(created_at__date__gte=start, created_at__date__lte=end)

    daily_growth = (
        users
        .annotate(date=TruncDate('created_at'))
        .values('date')
        .annotate(count=Count('id'))
        .order_by('date')
    )

    timestamp = timezone.now()
    filename = _generate_filename('user', fmt, timestamp)
    filepath = os.path.join(REPORTS_DIR, filename)

    if fmt == 'csv':
        with open(filepath, 'w', newline='', encoding='utf-8') as f:
            writer = csv.writer(f)
            writer.writerow(['Date', 'New Users'])

            for entry in daily_growth:
                writer.writerow([entry['date'].isoformat(), entry['count']])

            writer.writerow([])
            writer.writerow(['Summary'])
            writer.writerow(['Total New Users', users.count()])

            recent_cutoff = timezone.now() - timezone.timedelta(days=30)
            active_users = TransactionEvent.objects.filter(
                created_at__gte=recent_cutoff
            ).values('user_id').distinct().count()
            writer.writerow(['Active Users (30 days)', active_users])

    elif fmt == 'xlsx':
        import pandas as pd

        data = [
            {'Date': entry['date'].isoformat(), 'New Users': entry['count']}
            for entry in daily_growth
        ]
        df = pd.DataFrame(data)
        df.to_excel(filepath, index=False, engine='openpyxl')

    elif fmt == 'pdf':
        from reportlab.lib import colors
        from reportlab.lib.pagesizes import A4
        from reportlab.lib.styles import getSampleStyleSheet
        from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph, Spacer

        doc = SimpleDocTemplate(filepath, pagesize=A4)
        elements = []
        styles = getSampleStyleSheet()
        elements.append(Paragraph(f"User Report ({start} to {end})", styles['Title']))
        elements.append(Spacer(1, 20))

        data_rows = [['Date', 'New Users']]
        for entry in daily_growth:
            data_rows.append([entry['date'].isoformat(), str(entry['count'])])

        table = Table(data_rows, repeatRows=1)
        table.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (-1, 0), colors.HexColor('#4a148c')),
            ('TEXTCOLOR', (0, 0), (-1, 0), colors.white),
            ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
            ('FONTSIZE', (0, 0), (-1, -1), 9),
            ('GRID', (0, 0), (-1, -1), 0.5, colors.grey),
            ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, colors.HexColor('#f5f5f5')]),
        ]))
        elements.append(table)
        doc.build(elements)

    file_size = os.path.getsize(filepath)
    return filepath, file_size
