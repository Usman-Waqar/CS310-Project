import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/theme/app_colors.dart';
import '../../state/session_controller.dart';
import '../../widgets/common.dart';
import 'edit_profile_screen.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(sessionProvider).value;
    final phoneDisplay = (user?.maskedPhone != null && user!.maskedPhone!.isNotEmpty)
        ? user.maskedPhone!
        : (user?.phone != null && user!.phone!.isNotEmpty)
        ? user.phone!
        : 'Not set';

    return Scaffold(
      backgroundColor: AppColors.background,
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            ColoredBox(
              color: AppColors.navy,
              child: PageHeader(
                title: 'Profile',
                showBack: true,
                bottomPadding: 28,
                bottom: Center(
                  child: Column(
                    children: [
                      UserAvatar(user: user, size: 84),
                      const SizedBox(height: 12),
                      Text(
                        user?.fullName ?? 'Student Name',
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 20,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'SUCard holder',
                        style: TextStyle(
                          color: Colors.white.withValues(alpha: 0.78),
                          fontSize: 13.5,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  AppCard(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 18,
                      vertical: 8,
                    ),
                    child: Column(
                      children: [
                        _ProfileDetailRow(
                          label: 'Student ID',
                          value: user?.studentId ?? '00000',
                        ),
                        const Divider(height: 1, color: AppColors.divider),
                        _ProfileDetailRow(
                          label: 'SU username',
                          value: user?.suUsername ?? 'username',
                        ),
                        const Divider(height: 1, color: AppColors.divider),
                        _ProfileDetailRow(
                          label: 'University email',
                          value: user?.email ?? 'username@sabanciuniv.edu',
                        ),
                        const Divider(height: 1, color: AppColors.divider),
                        _ProfileDetailRow(
                          label: 'Phone',
                          value: phoneDisplay,
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                  OutlinedButton.icon(
                    onPressed: () {
                      Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder: (_) => const EditProfileScreen(),
                        ),
                      );
                    },
                    icon: const Icon(Icons.person_outline_rounded, size: 20),
                    label: const Text('Edit profile'),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ProfileDetailRow extends StatelessWidget {
  const _ProfileDetailRow({
    required this.label,
    required this.value,
  });

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(
                    color: AppColors.textMuted,
                    fontSize: 12.5,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  value,
                  style: const TextStyle(
                    color: AppColors.textPrimary,
                    fontSize: 15.5,
                    fontWeight: FontWeight.w600,
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