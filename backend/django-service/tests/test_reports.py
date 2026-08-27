import os
from datetime import timedelta
from decimal import Decimal

from django.test import TestCase, override_settings
from django.utils import timezone
from rest_framework import status
from rest_framework.test import APITestCase, APIClient

from analytics.models import User, TransactionEvent
from reports.models import Report


TEMP_MEDIA_ROOT = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'test_media')


@override_settings(MEDIA_ROOT=TEMP_MEDIA_ROOT)
class ReportCreateViewTest(APITestCase):
    def setUp(self):
        self.client = APIClient()
        self.user = User.objects.create_user(
            username='reportuser',
            email='report@example.com',
            password='reportpass123',
            user_id='user-report',
            first_name='Report',
            last_name='User',
        )
        self.client.force_authenticate(user=self.user)

        for i in range(10):
            TransactionEvent.objects.create(
                transaction_id=f'tx-rpt-{i:03d}',
                user_id='user-report',
                type='debit',
                category='airtime' if i % 2 == 0 else 'data',
                reference=f'ref-rpt-{i:03d}',
                amount=Decimal(f'{200 + i}.00'),
                fee=Decimal(f'{10 + i}.00'),
                status='successful',
                provider='mtn',
            )

    def test_create_transaction_report(self):
        response = self.client.post('/api/v1/reports/create/', {
            'type': 'transaction',
            'format': 'csv',
            'start_date': (timezone.now() - timedelta(days=7)).date().isoformat(),
            'end_date': timezone.now().date().isoformat(),
        })
        self.assertEqual(response.status_code, status.HTTP_201_CREATED)
        self.assertEqual(response.data['type'], 'transaction')
        self.assertEqual(response.data['status'], 'pending')

    def test_create_revenue_report(self):
        response = self.client.post('/api/v1/reports/create/', {
            'type': 'revenue',
            'format': 'csv',
        })
        self.assertEqual(response.status_code, status.HTTP_201_CREATED)

    def test_create_user_report(self):
        response = self.client.post('/api/v1/reports/create/', {
            'type': 'user',
            'format': 'xlsx',
        })
        self.assertEqual(response.status_code, status.HTTP_201_CREATED)

    def test_create_report_invalid_type(self):
        response = self.client.post('/api/v1/reports/create/', {
            'type': 'invalid',
        })
        self.assertEqual(response.status_code, status.HTTP_400_BAD_REQUEST)


class ReportListViewTest(APITestCase):
    def setUp(self):
        self.client = APIClient()
        self.user = User.objects.create_user(
            username='listuser',
            email='list@example.com',
            password='listpass123',
            user_id='user-list',
            first_name='List',
            last_name='User',
        )
        self.client.force_authenticate(user=self.user)

        Report.objects.create(
            type='transaction',
            format='csv',
            status='completed',
            generated_by=self.user,
            file_path='/tmp/test.csv',
            file_size=1024,
        )
        Report.objects.create(
            type='revenue',
            format='pdf',
            status='processing',
            generated_by=self.user,
        )

    def test_list_reports(self):
        response = self.client.get('/api/v1/reports/')
        self.assertEqual(response.status_code, status.HTTP_200_OK)
        self.assertEqual(response.data['count'], 2)

    def test_list_only_own_reports(self):
        other_user = User.objects.create_user(
            username='other',
            email='other@example.com',
            password='otherpass123',
            user_id='user-other',
            first_name='Other',
            last_name='User',
        )
        Report.objects.create(
            type='transaction',
            format='csv',
            status='completed',
            generated_by=other_user,
        )
        response = self.client.get('/api/v1/reports/')
        self.assertEqual(response.data['count'], 2)


class ReportDetailViewTest(APITestCase):
    def setUp(self):
        self.client = APIClient()
        self.user = User.objects.create_user(
            username='detailuser',
            email='detail@example.com',
            password='detailpass123',
            user_id='user-detail',
            first_name='Detail',
            last_name='User',
        )
        self.client.force_authenticate(user=self.user)
        self.report = Report.objects.create(
            type='transaction',
            format='xlsx',
            status='completed',
            generated_by=self.user,
            file_path='/tmp/test.xlsx',
            file_size=2048,
        )

    def test_get_detail(self):
        response = self.client.get(f'/api/v1/reports/{self.report.id}/')
        self.assertEqual(response.status_code, status.HTTP_200_OK)
        self.assertEqual(response.data['type'], 'transaction')
        self.assertEqual(response.data['format'], 'xlsx')

    def test_get_other_users_report(self):
        other_user = User.objects.create_user(
            username='other2',
            email='other2@example.com',
            password='otherpass123',
            user_id='user-other2',
            first_name='Other2',
            last_name='User',
        )
        other_report = Report.objects.create(
            type='revenue',
            format='csv',
            status='completed',
            generated_by=other_user,
        )
        response = self.client.get(f'/api/v1/reports/{other_report.id}/')
        self.assertEqual(response.status_code, status.HTTP_404_NOT_FOUND)


class ReportDownloadViewTest(APITestCase):
    def setUp(self):
        self.client = APIClient()
        self.user = User.objects.create_user(
            username='dluser',
            email='dl@example.com',
            password='dlpass123',
            user_id='user-dl',
            first_name='DL',
            last_name='User',
        )
        self.client.force_authenticate(user=self.user)

        os.makedirs(TEMP_MEDIA_ROOT, exist_ok=True)
        self.test_file = os.path.join(TEMP_MEDIA_ROOT, 'test_report.csv')
        with open(self.test_file, 'w') as f:
            f.write('col1,col2\nval1,val2\n')

        self.report = Report.objects.create(
            type='transaction',
            format='csv',
            status='completed',
            generated_by=self.user,
            file_path=self.test_file,
            file_size=os.path.getsize(self.test_file),
        )

    def tearDown(self):
        if os.path.exists(self.test_file):
            os.remove(self.test_file)
        if os.path.exists(TEMP_MEDIA_ROOT):
            os.rmdir(TEMP_MEDIA_ROOT)

    def test_download_completed_report(self):
        response = self.client.get(f'/api/v1/reports/{self.report.id}/download/')
        self.assertEqual(response.status_code, status.HTTP_200_OK)

    def test_download_pending_report_fails(self):
        self.report.status = 'pending'
        self.report.save()
        response = self.client.get(f'/api/v1/reports/{self.report.id}/download/')
        self.assertEqual(response.status_code, status.HTTP_400_BAD_REQUEST)

    def test_download_nonexistent_report(self):
        response = self.client.get('/api/v1/reports/99999/download/')
        self.assertEqual(response.status_code, status.HTTP_404_NOT_FOUND)


class ReportGenerateTaskTest(TestCase):
    def setUp(self):
        self.user = User.objects.create_user(
            username='taskuser',
            email='task@example.com',
            password='taskpass123',
            user_id='user-task',
            first_name='Task',
            last_name='User',
        )
        os.makedirs(TEMP_MEDIA_ROOT, exist_ok=True)

    def tearDown(self):
        import shutil
        if os.path.exists(TEMP_MEDIA_ROOT):
            shutil.rmtree(TEMP_MEDIA_ROOT)

    def test_generate_transaction_csv(self):
        from reports.generators import generate_transaction_report

        for i in range(5):
            TransactionEvent.objects.create(
                transaction_id=f'tx-gen-{i}',
                user_id='user-task',
                type='debit',
                category='airtime',
                reference=f'ref-gen-{i}',
                amount=Decimal('100.00'),
                fee=Decimal('5.00'),
                status='successful',
            )

        params = {
            'format': 'csv',
            'start_date': (timezone.now() - timedelta(days=1)).date().isoformat(),
            'end_date': (timezone.now() + timedelta(days=1)).date().isoformat(),
        }
        filepath, file_size = generate_transaction_report(params, self.user)
        self.assertTrue(os.path.exists(filepath))
        self.assertGreater(file_size, 0)
        os.remove(filepath)

    def test_generate_revenue_csv(self):
        from reports.generators import generate_revenue_report

        TransactionEvent.objects.create(
            transaction_id='tx-rev-gen',
            user_id='user-task',
            type='debit',
            category='data',
            reference='ref-rev-gen',
            amount=Decimal('1500.00'),
            fee=Decimal('75.00'),
            status='successful',
        )

        params = {
            'format': 'csv',
            'start_date': (timezone.now() - timedelta(days=1)).date().isoformat(),
            'end_date': (timezone.now() + timedelta(days=1)).date().isoformat(),
        }
        filepath, file_size = generate_revenue_report(params, self.user)
        self.assertTrue(os.path.exists(filepath))
        self.assertGreater(file_size, 0)
        os.remove(filepath)
