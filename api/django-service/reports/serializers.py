from rest_framework import serializers
from .models import Report


class ReportSerializer(serializers.ModelSerializer):
    generated_by_email = serializers.CharField(source='generated_by.email', read_only=True, default=None)

    class Meta:
        model = Report
        fields = [
            'id', 'type', 'format', 'status', 'generated_by', 'generated_by_email',
            'parameters', 'file_path', 'file_size', 'error_message',
            'created_at', 'completed_at',
        ]
        read_only_fields = [
            'id', 'status', 'file_path', 'file_size', 'error_message',
            'created_at', 'completed_at',
        ]


class ReportCreateSerializer(serializers.Serializer):
    type = serializers.ChoiceField(choices=Report.REPORT_TYPES)
    format = serializers.ChoiceField(choices=Report.FORMAT_CHOICES, default='csv')
    start_date = serializers.DateField(required=False)
    end_date = serializers.DateField(required=False)
    category = serializers.CharField(required=False)
    status = serializers.CharField(required=False)
    user_id = serializers.CharField(required=False)
