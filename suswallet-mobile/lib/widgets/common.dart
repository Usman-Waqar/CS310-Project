import 'package:flutter/material.dart';

import '../core/theme/app_colors.dart';
import '../models/user_profile.dart';

/// White rounded card used throughout the app.
class AppCard extends StatelessWidget {
  const AppCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(20),
    this.radius = 20,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final double radius;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: padding,
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(radius),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0F0A2467),
            blurRadius: 16,
            offset: Offset(0, 4),
          ),
        ],
      ),
      child: child,
    );
  }
}

/// Navy header whose colour continues [overlap] pixels behind [child], so the
/// first card appears to float half on the header (as in the wireframes).
class OverlapHeader extends StatelessWidget {
  const OverlapHeader({
    super.key,
    required this.header,
    required this.child,
    this.overlap = 48,
  });

  final Widget header;
  final Widget child;
  final double overlap;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ColoredBox(color: AppColors.navy, child: header),
        Stack(
          children: [
            Container(height: overlap, color: AppColors.navy),
            child,
          ],
        ),
      ],
    );
  }
}

/// Navy page header with optional back arrow, used by Settings sub-pages.
class PageHeader extends StatelessWidget {
  const PageHeader({
    super.key,
    required this.title,
    this.subtitle,
    this.showBack = false,
    this.bottom,
    this.bottomPadding = 24,
  });

  final String title;
  final String? subtitle;
  final bool showBack;
  final Widget? bottom;
  final double bottomPadding;

  @override
  Widget build(BuildContext context) {
    final top = MediaQuery.paddingOf(context).top;
    return Padding(
      padding: EdgeInsets.fromLTRB(showBack ? 8 : 24, top + 16, 24, bottomPadding),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              if (showBack)
                IconButton(
                  onPressed: () => Navigator.of(context).maybePop(),
                  icon: const Icon(Icons.arrow_back_ios_new_rounded),
                  color: Colors.white,
                  iconSize: 22,
                  tooltip: 'Back',
                ),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 28,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
          if (subtitle != null) ...[
            const SizedBox(height: 4),
            Padding(
              padding: EdgeInsets.only(left: showBack ? 16 : 0),
              child: Text(
                subtitle!,
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.85),
                  fontSize: 15,
                ),
              ),
            ),
          ],
          if (bottom != null) ...[
            const SizedBox(height: 16),
            bottom!,
          ],
        ],
      ),
    );
  }
}

/// Grey-blue informational note with an "i" icon.
class InfoNote extends StatelessWidget {
  const InfoNote(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.infoNote,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.info_outline, color: AppColors.primary, size: 20),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              text,
              style: const TextStyle(
                color: AppColors.textPrimary,
                fontSize: 13.5,
                height: 1.4,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Student photo, or initials when no photo has been uploaded
/// (Home header).
class UserAvatar extends StatelessWidget {
  const UserAvatar({super.key, required this.user, this.size = 64});

  final UserProfile? user;
  final double size;

  @override
  Widget build(BuildContext context) {
    final url = user?.resolvedAvatarUrl;

    final fallback = Center(
      child: Text(
        user?.initials ?? 'SU',
        style: TextStyle(
          color: Colors.white,
          fontSize: size * 0.34,
          fontWeight: FontWeight.w600,
        ),
      ),
    );

    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: AppColors.navySoft,
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.35),
          width: 2,
        ),
      ),
      clipBehavior: Clip.antiAlias,
      child: url == null
          ? fallback
          : Image.network(
              url,
              fit: BoxFit.cover,
              errorBuilder: (context, error, stackTrace) => fallback,
            ),
    );
  }
}

/// "● SUCard Active" pill on the navy Home header.
class StatusPill extends StatelessWidget {
  const StatusPill({super.key, required this.label, this.active = true});

  final String label;
  final bool active;

  @override
  Widget build(BuildContext context) {
    final dotColor = active ? AppColors.cafeteria : AppColors.printing;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 8,
            height: 8,
            decoration: BoxDecoration(color: dotColor, shape: BoxShape.circle),
          ),
          const SizedBox(width: 6),
          Text(
            label,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 13,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }
}

/// Inline loading / error placeholder for async sections.
class AsyncSectionMessage extends StatelessWidget {
  const AsyncSectionMessage({
    super.key,
    this.message,
    this.onRetry,
    this.loading = false,
  });

  final String? message;
  final VoidCallback? onRetry;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 24),
      child: Center(
        child: loading
            ? const SizedBox(
                width: 28,
                height: 28,
                child: CircularProgressIndicator(strokeWidth: 2.5),
              )
            : Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    message ?? '',
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: AppColors.textMuted),
                  ),
                  if (onRetry != null)
                    TextButton(onPressed: onRetry, child: const Text('Retry')),
                ],
              ),
      ),
    );
  }
}

void showAppSnack(BuildContext context, String message, {bool error = false}) {
  final messenger = ScaffoldMessenger.maybeOf(context);
  if (messenger == null) return;
  messenger
    ..hideCurrentSnackBar()
    ..showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: error ? AppColors.danger : AppColors.navy,
      ),
    );
}
