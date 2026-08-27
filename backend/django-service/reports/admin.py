from django.contrib import admin
from .models import Report


@admin.register(Report)
class ReportAdmin(admin.ModelAdmin):
    list_display = ['id', 'type', 'format', 'status', 'generated_by', 'file_size', 'created_at', 'completed_at']
    list_filter = ['type', 'format', 'status']
    search_fields = ['id', 'generated_by__email']
    readonly_fields = ['created_at', 'completed_at', 'file_path', 'file_size', 'error_message']
