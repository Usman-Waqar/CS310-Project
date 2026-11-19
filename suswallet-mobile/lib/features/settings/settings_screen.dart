import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/theme/app_colors.dart';
import '../../models/user_profile.dart';
import '../../state/session_controller.dart';
import '../../state/shell_tab.dart';
import '../../widgets/common.dart';

class SettingsScreen extends ConsumerWidget {
  const SettingsScreen({
    super.key,
    this.onOpenProfile,
    this.onOpenSecurity,
    this.onOpenTerms,
    this.onOpenAbout,
  });

  final VoidCallback? onOpenProfile;
  final VoidCallback? onOpenSecurity;
  final VoidCallback? onOpenTerms;
  final VoidCallback? onOpenAbout;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(sessionProvider).value;

    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _SettingsHeader(user: user),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
            child: AppCard(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Column(
                children: [
                  _SettingsMenuTile(
                    icon: Icons.person_outline_rounded,
                    iconColor: AppColors.primary,
                    title: 'Profile',
                    onTap: onOpenProfile ?? () {},
                  ),
                  const Divider(height: 1, indent: 64, color: AppColors.divider),
                  _SettingsMenuTile(
                    icon: Icons.verified_user_outlined,
                    iconColor: AppColors.success,
                    title: 'SUCard & Security',
                    trailing: _ActiveBadge(
                      label: user?.sucardStatus ?? 'Active',
                      active: user?.isActive ?? true,
                    ),
                    onTap: onOpenSecurity ?? () {},
                  ),
                  const Divider(height: 1, indent: 64, color: AppColors.divider),
                  _SettingsMenuTile(
                    icon: Icons.description_outlined,
                    iconColor: AppColors.navy,
                    title: 'Terms & Conditions',
                    onTap: onOpenTerms ?? () {},
                  ),
                  const Divider(height: 1, indent: 64, color: AppColors.divider),
                  _SettingsMenuTile(
                    icon: Icons.info_outline_rounded,
                    iconColor: AppColors.navy,
                    title: 'About SUSWallet',
                    onTap: onOpenAbout ?? () {},
                  ),
                  const Divider(height: 1, indent: 64, color: AppColors.divider),
                  _SettingsMenuTile(
                    icon: Icons.logout_rounded,
                    iconColor: AppColors.danger,
                    title: 'Log Out',
                    titleColor: AppColors.danger,
                    showChevron: false,
                    onTap: () => _confirmLogout(context, ref),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _confirmLogout(BuildContext context, WidgetRef ref) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text(
          'Log out of SUSWallet?',
          style: TextStyle(fontWeight: FontWeight.w700, fontSize: 18),
        ),
        content: const Text(
          'You will need your @sabanciuniv.edu email and password to sign back in.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: AppColors.danger,
              minimumSize: const Size(96, 42),
            ),
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Log Out'),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      await ref.read(sessionProvider.notifier).logout();
      ref.read(shellTabProvider.notifier).select(ShellTab.home);
    }
  }
}

class _SettingsHeader extends StatelessWidget {
  const _SettingsHeader({required this.user});

  final UserProfile? user;

  @override
  Widget build(BuildContext context) {
    final top = MediaQuery.paddingOf(context).top;
    return Container(
      color: AppColors.navy,
      padding: EdgeInsets.fromLTRB(24, top + 20, 24, 28),
      child: Row(
        children: [
          UserAvatar(user: user, size: 68),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  user?.fullName ?? 'Student Name',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  user?.email ?? 'username@sabanciuniv.edu',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.8),
                    fontSize: 13.5,
                  ),
                ),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.14),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    'Student ID ${user?.studentId ?? '00000'}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 12.5,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SettingsMenuTile extends StatelessWidget {
  const _SettingsMenuTile({
    required this.icon,
    required this.iconColor,
    required this.title,
    required this.onTap,
    this.titleColor = AppColors.textPrimary,
    this.trailing,
    this.showChevron = true,
  });

  final IconData icon;
  final Color iconColor;
  final String title;
  final Color titleColor;
  final Widget? trailing;
  final bool showChevron;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 15),
        child: Row(
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: iconColor.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(11),
              ),
              child: Icon(icon, color: iconColor, size: 20),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Text(
                title,
                style: TextStyle(
                  color: titleColor,
                  fontSize: 15.5,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            if (trailing != null) ...[
              trailing!,
              const SizedBox(width: 8),
            ],
            if (showChevron)
              const Icon(
                Icons.chevron_right_rounded,
                color: AppColors.textSubtle,
                size: 22,
              ),
          ],
        ),
      ),
    );
  }
}

class _ActiveBadge extends StatelessWidget {
  const _ActiveBadge({required this.label, required this.active});

  final String label;
  final bool active;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: active ? AppColors.successSoft : AppColors.dangerSoft,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: active ? AppColors.success : AppColors.danger,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}