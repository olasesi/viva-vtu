import os

from django.conf import settings
from django.http import FileResponse, Http404
from rest_framework import generics, status
from rest_framework.permissions import IsAuthenticated
from rest_framework.response import Response
from rest_framework.views import APIView

from .models import Report
from .serializers import ReportSerializer, ReportCreateSerializer
from .tasks import generate_report_task


class ReportListView(generics.ListAPIView):
    """List user's generated reports."""
    serializer_class = ReportSerializer
    permission_classes = [IsAuthenticated]

    def get_queryset(self):
        return Report.objects.filter(generated_by=self.request.user)


class ReportCreateView(APIView):
    """Trigger report generation (queues Celery task)."""
    permission_classes = [IsAuthenticated]

    def post(self, request):
        serializer = ReportCreateSerializer(data=request.data)
        serializer.is_valid(raise_exception=True)

        report = Report.objects.create(
            type=serializer.validated_data['type'],
            format=serializer.validated_data.get('format', 'csv'),
            generated_by=request.user,
            parameters={
                'start_date': str(serializer.validated_data.get('start_date', '')),
                'end_date': str(serializer.validated_data.get('end_date', '')),
                'category': serializer.validated_data.get('category', ''),
                'status': serializer.validated_data.get('status', ''),
                'user_id': serializer.validated_data.get('user_id', ''),
                'format': serializer.validated_data.get('format', 'csv'),
            },
        )

        generate_report_task.delay(report.id)

        return Response(
            ReportSerializer(report).data,
            status=status.HTTP_201_CREATED,
        )


class ReportDetailView(generics.RetrieveAPIView):
    """Report status and metadata."""
    serializer_class = ReportSerializer
    permission_classes = [IsAuthenticated]

    def get_queryset(self):
        return Report.objects.filter(generated_by=self.request.user)


class ReportDownloadView(APIView):
    """Download generated report file."""
    permission_classes = [IsAuthenticated]

    def get(self, request, pk):
        try:
            report = Report.objects.get(id=pk, generated_by=request.user)
        except Report.DoesNotExist:
            return Response(
                {'error': 'Report not found'},
                status=status.HTTP_404_NOT_FOUND,
            )

        if report.status != 'completed':
            return Response(
                {'error': f'Report is not ready. Current status: {report.status}'},
                status=status.HTTP_400_BAD_REQUEST,
            )

        if not report.file_path or not os.path.exists(report.file_path):
            return Response(
                {'error': 'Report file not found on disk'},
                status=status.HTTP_404_NOT_FOUND,
            )

        content_types = {
            'csv': 'text/csv',
            'xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf': 'application/pdf',
        }

        filename = os.path.basename(report.file_path)
        response = FileResponse(
            open(report.file_path, 'rb'),
            content_type=content_types.get(report.format, 'application/octet-stream'),
        )
        response['Content-Disposition'] = f'attachment; filename="{filename}"'
        return response
