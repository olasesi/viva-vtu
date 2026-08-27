from django.urls import path
from . import views

app_name = 'analytics'

urlpatterns = [
    path('dashboard/', views.DashboardView.as_view(), name='dashboard'),
    path('transactions/', views.TransactionEventListView.as_view(), name='transaction-list'),
    path('transactions/<str:transaction_id>/', views.TransactionEventDetailView.as_view(), name='transaction-detail'),
    path('transactions/analytics/', views.TransactionAnalyticsView.as_view(), name='transaction-analytics'),
    path('revenue/', views.RevenueAnalyticsView.as_view(), name='revenue-analytics'),
    path('users/', views.UserAnalyticsView.as_view(), name='user-analytics'),
    path('sync/', views.SyncTransactionsView.as_view(), name='sync-transactions'),
    path('alerts/', views.SystemAlertListView.as_view(), name='alert-list'),
    path('alerts/create/', views.SystemAlertCreateView.as_view(), name='alert-create'),
]
