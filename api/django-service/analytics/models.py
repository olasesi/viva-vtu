from django.db import models
from django.contrib.auth.models import AbstractUser


class User(AbstractUser):
    """Extended user for analytics tracking only - synced from auth-service"""
    user_id = models.CharField(max_length=36, unique=True, db_index=True)
    email = models.EmailField(unique=True)
    phone = models.CharField(max_length=20, null=True, blank=True)
    first_name = models.CharField(max_length=100)
    last_name = models.CharField(max_length=100)
    is_active = models.BooleanField(default=True)
    created_at = models.DateTimeField(auto_now_add=True)
    synced_at = models.DateTimeField(auto_now=True)

    class Meta:
        ordering = ['-created_at']

    def __str__(self):
        return f"{self.first_name} {self.last_name} ({self.email})"


class TransactionEvent(models.Model):
    """Raw transaction events synced from billing service"""
    EVENT_TYPES = [
        ('airtime', 'Airtime'),
        ('data', 'Data'),
        ('electricity', 'Electricity'),
        ('cable', 'Cable TV'),
        ('wallet_fund', 'Wallet Fund'),
        ('transfer', 'Transfer'),
    ]
    STATUS_CHOICES = [
        ('pending', 'Pending'),
        ('successful', 'Successful'),
        ('failed', 'Failed'),
        ('reversed', 'Reversed'),
    ]

    transaction_id = models.CharField(max_length=36, unique=True, db_index=True)
    user_id = models.CharField(max_length=36, db_index=True)
    type = models.CharField(max_length=20, choices=[('credit', 'Credit'), ('debit', 'Debit')])
    category = models.CharField(max_length=20, choices=EVENT_TYPES)
    reference = models.CharField(max_length=100, unique=True)
    amount = models.DecimalField(max_digits=12, decimal_places=2)
    fee = models.DecimalField(max_digits=12, decimal_places=2, default=0)
    status = models.CharField(max_length=20, choices=STATUS_CHOICES, default='pending')
    provider = models.CharField(max_length=50, null=True, blank=True)
    metadata = models.JSONField(default=dict, blank=True)
    created_at = models.DateTimeField(auto_now_add=True)
    completed_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        ordering = ['-created_at']
        indexes = [
            models.Index(fields=['user_id', 'category']),
            models.Index(fields=['status', 'created_at']),
            models.Index(fields=['category', 'created_at']),
        ]

    def __str__(self):
        return f"{self.transaction_id} - {self.category} - {self.amount}"


class DailyAggregate(models.Model):
    """Pre-computed daily aggregates for fast dashboard queries"""
    date = models.DateField(unique=True, db_index=True)
    total_users = models.IntegerField(default=0)
    new_users = models.IntegerField(default=0)
    total_transactions = models.IntegerField(default=0)
    successful_transactions = models.IntegerField(default=0)
    failed_transactions = models.IntegerField(default=0)
    total_revenue = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    total_fees = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    airtime_volume = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    data_volume = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    electricity_volume = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    cable_volume = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        ordering = ['-date']

    def __str__(self):
        return f"Aggregate for {self.date}"


class SystemAlert(models.Model):
    """System alerts for monitoring"""
    SEVERITY_CHOICES = [
        ('info', 'Info'),
        ('warning', 'Warning'),
        ('critical', 'Critical'),
    ]
    title = models.CharField(max_length=200)
    message = models.TextField()
    severity = models.CharField(max_length=20, choices=SEVERITY_CHOICES, default='info')
    is_resolved = models.BooleanField(default=False)
    created_at = models.DateTimeField(auto_now_add=True)
    resolved_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        ordering = ['-created_at']

    def __str__(self):
        return f"[{self.severity.upper()}] {self.title}"
