import logging
import os

from celery import shared_task
from django.utils import timezone

logger = logging.getLogger('reports')


@shared_task(name='reports.generate_report')
def generate_report_task(report_id):
    """Generate a report in the background."""
    from .models import Report
    from .generators import (
        generate_transaction_report,
        generate_revenue_report,
        generate_user_report,
    )

    try:
        report = Report.objects.get(id=report_id)
        report.status = 'processing'
        report.save(update_fields=['status'])

        generators = {
            'transaction': generate_transaction_report,
            'revenue': generate_revenue_report,
            'user': generate_user_report,
        }

        generator = generators.get(report.type)
        if not generator:
            raise ValueError(f"Unknown report type: {report.type}")

        filepath, file_size = generator(report.parameters, report.generated_by)

        report.file_path = filepath
        report.file_size = file_size
        report.status = 'completed'
        report.completed_at = timezone.now()
        report.save(update_fields=['file_path', 'file_size', 'status', 'completed_at'])

        logger.info(f"Report {report_id} generated successfully: {filepath}")
        return f"Report {report_id} completed"

    except Report.DoesNotExist:
        logger.error(f"Report {report_id} not found")
    except Exception as e:
        logger.error(f"Report generation failed for {report_id}: {e}")
        try:
            report = Report.objects.get(id=report_id)
            report.status = 'failed'
            report.error_message = str(e)
            report.save(update_fields=['status', 'error_message'])
        except Report.DoesNotExist:
            pass
        raise
