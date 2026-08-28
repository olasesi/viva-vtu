from datetime import timedelta
from decimal import Decimal

from django.test import TestCase
from django.utils import timezone
from rest_framework import status
from rest_framework.test import APITestCase, APIClient

from analytics.models import User, TransactionEvent, DailyAggregate, SystemAlert


class DashboardViewTest(APITestCase):
    def setUp(self):
        self.client = APIClient()
        self.user = User.objects.create_user(
            username='testuser',
            email='test@example.com',
            password='testpass123',
            user_id='user-001',
            first_name='Test',
            last_name='User',
        )
        self.client.force_authenticate(user=self.user)
        self.today = timezone.now().date()

    def test_dashboard_returns_data(self):
        DailyAggregate.objects.create(
            date=self.today,
            total_users=100,
            new_users=5,
            total_transactions=50,
            successful_transactions=45,
            failed_transactions=5,
            total_revenue=Decimal('15000.00'),
            total_fees=Decimal('750.00'),
            airtime_volume=Decimal('5000.00'),
            data_volume=Decimal('4000.00'),
            electricity_volume=Decimal('3500.00'),
            cable_volume=Decimal('2500.00'),
        )
        response = self.client.get('/api/v1/analytics/dashboard/')
        self.assertEqual(response.status_code, status.HTTP_200_OK)
        self.assertIn('today', response.data)
        self.assertIn('totals', response.data)
        self.assertIn('weekly_trend', response.data)
        self.assertIn('category_breakdown', response.data)
        self.assertIn('recent_alerts', response.data)

    def test_dashboard_with_no_data(self):
        response = self.client.get('/api/v1/analytics/dashboard/')
        self.assertEqual(response.status_code, status.HTTP_200_OK)
        self.assertEqual(response.data['today']['total_transactions'], 0)


class TransactionEventListViewTest(APITestCase):
    def setUp(self):
        self.client = APIClient()
        self.user = User.objects.create_user(
            username='testuser2',
            email='test2@example.com',
            password='testpass123',
            user_id='user-002',
            first_name='Test2',
            last_name='User2',
        )
        self.client.force_authenticate(user=self.user)

        for i in range(25):
            TransactionEvent.objects.create(
                transaction_id=f'tx-{i:03d}',
                user_id='user-002',
                type='debit',
                category='airtime' if i % 2 == 0 else 'data',
                reference=f'ref-{i:03d}',
                amount=Decimal(f'{100 + i}.00'),
                fee=Decimal(f'{5 + i}.00'),
                status='successful',
                provider='mtn',
            )

    def test_list_transactions_paginated(self):
        response = self.client.get('/api/v1/analytics/transactions/')
        self.assertEqual(response.status_code, status.HTTP_200_OK)
        self.assertIn('results', response.data)
        self.assertEqual(len(response.data['results']), 20)
        self.assertEqual(response.data['count'], 25)

    def test_filter_by_category(self):
        response = self.client.get('/api/v1/analytics/transactions/', {'category': 'airtime'})
        self.assertEqual(response.status_code, status.HTTP_200_OK)
        self.assertEqual(response.data['count'], 13)

    def test_filter_by_status(self):
        response = self.client.get('/api/v1/analytics/transactions/', {'status': 'successful'})
        self.assertEqual(response.status_code, status.HTTP_200_OK)
        self.assertEqual(response.data['count'], 25)


class TransactionEventDetailViewTest(APITestCase):
    def setUp(self):
        self.client = APIClient()
        self.user = User.objects.create_user(
            username='testuser3',
            email='test3@example.com',
            password='testpass123',
            user_id='user-003',
            first_name='Test3',
            last_name='User3',
        )
        self.client.force_authenticate(user=self.user)
        self.tx = TransactionEvent.objects.create(
            transaction_id='tx-detail-001',
            user_id='user-003',
            type='credit',
            category='wallet_fund',
            reference='ref-detail-001',
            amount=Decimal('5000.00'),
            fee=Decimal('0.00'),
            status='successful',
        )

    def test_get_detail(self):
        response = self.client.get('/api/v1/analytics/transactions/tx-detail-001/')
        self.assertEqual(response.status_code, status.HTTP_200_OK)
        self.assertEqual(response.data['transaction_id'], 'tx-detail-001')
        self.assertEqual(response.data['category'], 'wallet_fund')

    def test_get_nonexistent(self):
        response = self.client.get('/api/v1/analytics/transactions/nonexistent/')
        self.assertEqual(response.status_code, status.HTTP_404_NOT_FOUND)


class SyncTransactionsViewTest(APITestCase):
    def setUp(self):
        self.client = APIClient()
        self.sync_url = '/api/v1/analytics/sync/'

    def test_sync_with_valid_api_key(self):
        from django.conf import settings
        self.client.credentials(HTTP_X_INTERNAL_API_KEY=settings.INTERNAL_API_KEY)

        payload = {
            'events': [
                {
                    'transaction_id': 'tx-sync-001',
                    'user_id': 'user-sync-001',
                    'type': 'debit',
                    'category': 'airtime',
                    'reference': 'ref-sync-001',
                    'amount': '200.00',
                    'fee': '10.00',
                    'status': 'successful',
                    'provider': 'mtn',
                },
                {
                    'transaction_id': 'tx-sync-002',
                    'user_id': 'user-sync-001',
                    'type': 'debit',
                    'category': 'data',
                    'reference': 'ref-sync-002',
                    'amount': '1500.00',
                    'fee': '50.00',
                    'status': 'successful',
                    'provider': 'airtel',
                },
            ]
        }
        response = self.client.post(self.sync_url, payload, format='json')
        self.assertEqual(response.status_code, status.HTTP_200_OK)
        self.assertEqual(response.data['created'], 2)
        self.assertEqual(response.data['updated'], 0)
        self.assertEqual(TransactionEvent.objects.count(), 2)

    def test_sync_without_api_key(self):
        response = self.client.post(self.sync_url, {'events': []}, format='json')
        self.assertEqual(response.status_code, status.HTTP_403_FORBIDDEN)

    def test_sync_updates_existing(self):
        from django.conf import settings
        self.client.credentials(HTTP_X_INTERNAL_API_KEY=settings.INTERNAL_API_KEY)
        TransactionEvent.objects.create(
            transaction_id='tx-existing',
            user_id='user-1',
            type='debit',
            category='airtime',
            reference='ref-existing',
            amount=Decimal('100.00'),
            status='pending',
        )

        payload = {
            'events': [
                {
                    'transaction_id': 'tx-existing',
                    'user_id': 'user-1',
                    'type': 'debit',
                    'category': 'airtime',
                    'reference': 'ref-existing',
                    'amount': '100.00',
                    'status': 'successful',
                },
            ]
        }
        response = self.client.post(self.sync_url, payload, format='json')
        self.assertEqual(response.status_code, status.HTTP_200_OK)
        self.assertEqual(response.data['updated'], 1)
        self.tx = TransactionEvent.objects.get(transaction_id='tx-existing')
        self.assertEqual(self.tx.status, 'successful')


class TransactionAnalyticsViewTest(APITestCase):
    def setUp(self):
        self.client = APIClient()
        self.user = User.objects.create_user(
            username='testana',
            email='ana@example.com',
            password='testpass123',
            user_id='user-ana',
            first_name='Ana',
            last_name='Lytics',
        )
        self.client.force_authenticate(user=self.user)

    def test_transaction_analytics_empty(self):
        response = self.client.get('/api/v1/analytics/transactions/analytics/')
        self.assertEqual(response.status_code, status.HTTP_200_OK)
        self.assertIn('daily_aggregates', response.data)
        self.assertIn('totals', response.data)


class RevenueAnalyticsViewTest(APITestCase):
    def setUp(self):
        self.client = APIClient()
        self.user = User.objects.create_user(
            username='testrev',
            email='rev@example.com',
            password='testpass123',
            user_id='user-rev',
            first_name='Rev',
            last_name='Enue',
        )
        self.client.force_authenticate(user=self.user)

    def test_revenue_analytics_empty(self):
        response = self.client.get('/api/v1/analytics/revenue/')
        self.assertEqual(response.status_code, status.HTTP_200_OK)
        self.assertIn('breakdown', response.data)
        self.assertIn('total_revenue', response.data)


class UserAnalyticsViewTest(APITestCase):
    def setUp(self):
        self.client = APIClient()
        self.user = User.objects.create_user(
            username='testusr',
            email='usr@example.com',
            password='testpass123',
            user_id='user-usr',
            first_name='Usr',
            last_name='Analytics',
        )
        self.client.force_authenticate(user=self.user)

    def test_user_analytics(self):
        response = self.client.get('/api/v1/analytics/users/')
        self.assertEqual(response.status_code, status.HTTP_200_OK)
        self.assertIn('growth', response.data)
        self.assertIn('summary', response.data)


class SystemAlertTest(APITestCase):
    def setUp(self):
        self.client = APIClient()
        self.admin_user = User.objects.create_user(
            username='admin',
            email='admin@example.com',
            password='adminpass123',
            user_id='user-admin',
            first_name='Admin',
            last_name='User',
            is_staff=True,
        )
        self.regular_user = User.objects.create_user(
            username='regular',
            email='regular@example.com',
            password='regpass123',
            user_id='user-regular',
            first_name='Regular',
            last_name='User',
        )

    def test_list_alerts(self):
        SystemAlert.objects.create(title='Test Alert', message='Test message', severity='info')
        self.client.force_authenticate(user=self.regular_user)
        response = self.client.get('/api/v1/analytics/alerts/')
        self.assertEqual(response.status_code, status.HTTP_200_OK)

    def test_create_alert_admin(self):
        self.client.force_authenticate(user=self.admin_user)
        response = self.client.post('/api/v1/analytics/alerts/create/', {
            'title': 'System Alert',
            'message': 'System is running low on disk space',
            'severity': 'warning',
        })
        self.assertEqual(response.status_code, status.HTTP_201_CREATED)
        self.assertEqual(SystemAlert.objects.count(), 1)

    def test_create_alert_non_admin_rejected(self):
        self.client.force_authenticate(user=self.regular_user)
        response = self.client.post('/api/v1/analytics/alerts/create/', {
            'title': 'Test',
            'message': 'Test',
            'severity': 'info',
        })
        self.assertEqual(response.status_code, status.HTTP_403_FORBIDDEN)
