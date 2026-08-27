from django.db import models


class Report(models.Model):
    REPORT_TYPES = [
        ('transaction', 'Transaction Report'),
        ('revenue', 'Revenue Report'),
        ('user', 'User Report'),
    ]
    FORMAT_CHOICES = [
        ('csv', 'CSV'),
        ('xlsx', 'Excel'),
        ('pdf', 'PDF'),
    ]
    STATUS_CHOICES = [
        ('pending', 'Pending'),
        ('processing', 'Processing'),
        ('completed', 'Completed'),
        ('failed', 'Failed'),
    ]

    id = models.AutoField(primary_key=True)
    type = models.CharField(max_length=20, choices=REPORT_TYPES)
    format = models.CharField(max_length=10, choices=FORMAT_CHOICES, default='csv')
    status = models.CharField(max_length=20, choices=STATUS_CHOICES, default='pending')
    generated_by = models.ForeignKey('analytics.User', on_delete=models.SET_NULL, null=True)
    parameters = models.JSONField(default=dict)
    file_path = models.CharField(max_length=500, null=True, blank=True)
    file_size = models.IntegerField(null=True, blank=True)
    error_message = models.TextField(null=True, blank=True)
    created_at = models.DateTimeField(auto_now_add=True)
    completed_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        ordering = ['-created_at']

    def __str__(self):
        return f"Report {self.id} - {self.type} ({self.status})"
