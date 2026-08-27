from django.contrib import admin
from .models import User, TransactionEvent, DailyAggregate, SystemAlert


@admin.register(User)
class UserAdmin(admin.ModelAdmin):
    list_display = ['user_id', 'email', 'first_name', 'last_name', 'is_active', 'created_at']
    search_fields = ['email', 'first_name', 'last_name', 'user_id']
    list_filter = ['is_active', 'created_at']
    readonly_fields = ['created_at', 'synced_at']


@admin.register(TransactionEvent)
class TransactionEventAdmin(admin.ModelAdmin):
    list_display = [
        'transaction_id', 'user_id', 'type', 'category', 'amount',
        'status', 'provider', 'created_at',
    ]
    search_fields = ['transaction_id', 'user_id', 'reference']
    list_filter = ['type', 'category', 'status', 'provider', 'created_at']
    readonly_fields = ['created_at', 'completed_at']


@admin.register(DailyAggregate)
class DailyAggregateAdmin(admin.ModelAdmin):
    list_display = [
        'date', 'total_users', 'total_transactions', 'successful_transactions',
        'total_revenue', 'total_fees',
    ]
    list_filter = ['date']
    readonly_fields = ['updated_at']


@admin.register(SystemAlert)
class SystemAlertAdmin(admin.ModelAdmin):
    list_display = ['title', 'severity', 'is_resolved', 'created_at', 'resolved_at']
    list_filter = ['severity', 'is_resolved']
    search_fields = ['title', 'message']
    readonly_fields = ['created_at', 'resolved_at']
