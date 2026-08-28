from rest_framework import serializers
from .models import User, TransactionEvent, DailyAggregate, SystemAlert


class UserSerializer(serializers.ModelSerializer):
    full_name = serializers.SerializerMethodField()

    class Meta:
        model = User
        fields = [
            'id', 'user_id', 'email', 'phone', 'first_name', 'last_name',
            'full_name', 'is_active', 'created_at', 'synced_at',
        ]
        read_only_fields = ['id', 'created_at', 'synced_at']

    def get_full_name(self, obj):
        return f"{obj.first_name} {obj.last_name}".strip()


class TransactionEventSerializer(serializers.ModelSerializer):
    class Meta:
        model = TransactionEvent
        fields = [
            'id', 'transaction_id', 'user_id', 'type', 'category', 'reference',
            'amount', 'fee', 'status', 'provider', 'metadata',
            'created_at', 'completed_at',
        ]
        read_only_fields = ['id', 'created_at']


class DailyAggregateSerializer(serializers.ModelSerializer):
    success_rate = serializers.SerializerMethodField()

    class Meta:
        model = DailyAggregate
        fields = [
            'id', 'date', 'total_users', 'new_users', 'total_transactions',
            'successful_transactions', 'failed_transactions', 'total_revenue',
            'total_fees', 'airtime_volume', 'data_volume', 'electricity_volume',
            'cable_volume', 'success_rate', 'updated_at',
        ]
        read_only_fields = ['id', 'updated_at']

    def get_success_rate(self, obj):
        if obj.total_transactions == 0:
            return 0.0
        return round((obj.successful_transactions / obj.total_transactions) * 100, 2)


class SystemAlertSerializer(serializers.ModelSerializer):
    class Meta:
        model = SystemAlert
        fields = [
            'id', 'title', 'message', 'severity', 'is_resolved',
            'created_at', 'resolved_at',
        ]
        read_only_fields = ['id', 'created_at']


class DashboardSerializer(serializers.Serializer):
    today = DailyAggregateSerializer()
    totals = serializers.DictField()
    weekly_trend = DailyAggregateSerializer(many=True)
    category_breakdown = serializers.DictField()
    recent_alerts = SystemAlertSerializer(many=True)


class RevenueBreakdownSerializer(serializers.Serializer):
    category = serializers.CharField()
    volume = serializers.DecimalField(max_digits=14, decimal_places=2)
    fees = serializers.DecimalField(max_digits=14, decimal_places=2)
    count = serializers.IntegerField()
    percentage = serializers.FloatField()


class UserGrowthSerializer(serializers.Serializer):
    date = serializers.DateField()
    new_users = serializers.IntegerField()
    total_users = serializers.IntegerField()
    active_users = serializers.IntegerField()
