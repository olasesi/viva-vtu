from django.conf import settings
from rest_framework.permissions import BasePermission


class IsServiceAccount(BasePermission):
    """Validate internal API key for service-to-service calls."""

    def has_permission(self, request, view):
        api_key = request.headers.get('X-Internal-API-Key', '')
        return api_key and api_key == settings.INTERNAL_API_KEY


class IsAdminUser(BasePermission):
    """Checks is_staff on the authenticated user."""

    def has_permission(self, request, view):
        return bool(request.user and request.user.is_authenticated and request.user.is_staff)
