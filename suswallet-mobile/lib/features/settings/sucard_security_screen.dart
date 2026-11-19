import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/theme/app_colors.dart';
import '../../state/session_controller.dart';
import '../../widgets/common.dart';
import 'change_password_screen.dart';

class SUCardSecurityScreen extends ConsumerWidget {
  const SUCardSecurityScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(sessionProvider).value;
    final holderName = (user?.fullName ?? 'CARDHOLDER NAME').toUpperCase();
    final isActive = user?.isActive ?? true;

    return Scaffold(
      backgroundColor: AppColors.background,
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const ColoredBox(
              color: AppColors.navy,
              child: PageHeader(
                title: 'SUCard & Security',
                showBack: true,
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _VirtualSUCard(
                    holderName: holderName,
                    studentId: user?.studentId ?? '00000',
                    statusLabel: user?.sucardStatus ?? 'Active',
                    isActive: isActive,
                  ),
                  const SizedBox(height: 18),
                  AppCard(
                    padding: EdgeInsets.zero,
                    child: InkWell(
                      borderRadius: BorderRadius.circular(20),
                      onTap: () {
                        Navigator.of(context).push(
                          MaterialPageRoute<void>(
                            builder: (_) => const ChangePasswordScreen(),
                          ),
                        );
                      },
                      child: Padding(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 18,
                          vertical: 16,
                        ),
                        child: Row(
                          children: [
                            Container(
                              width: 40,
                              height: 40,
                              decoration: BoxDecoration(
                                color: AppColors.primarySoft,
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: const Icon(
                                Icons.lock_outline_rounded,
                                color: AppColors.primary,
                                size: 20,
                              ),
                            ),
                            const SizedBox(width: 14),
                            const Expanded(
                              child: Text(
                                'Change password',
                                style: TextStyle(
                                  color: AppColors.textPrimary,
                                  fontSize: 15.5,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                            const Icon(
                              Icons.chevron_right_rounded,
                              color: AppColors.textSubtle,
                              size: 22,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  const InfoNote(
                    'Lost your SUCard? Contact the SUCard Office to have it '
                        'blocked. Your balances stay safe.',
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

class _VirtualSUCard extends StatelessWidget {
  const _VirtualSUCard({
    required this.holderName,
    required this.studentId,
    required this.statusLabel,
    required this.isActive,
  });

  final String holderName;
  final String studentId;
  final String statusLabel;
  final bool isActive;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 192,
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(22),
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            Color(0xFF15459C),
            Color(0xFF2F87E8),
          ],
        ),
        boxShadow: const [
          BoxShadow(
            color: Color(0x260A2467),
            blurRadius: 18,
            offset: Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Image.asset(
                'assets/images/sucard_badge.png',
                width: 38,
                height: 38,
                errorBuilder: (_, _, _) => const Icon(
                  Icons.credit_card_rounded,
                  color: Colors.white,
                  size: 32,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'SUCard',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    Text(
                      'Sabancı University',
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.82),
                        fontSize: 12.5,
                      ),
                    ),
                  ],
                ),
              ),
              StatusPill(label: statusLabel, active: isActive),
            ],
          ),
          const Spacer(),
          Text(
            'ID $studentId',
            style: TextStyle(
              color: Colors.white.withValues(alpha: 0.8),
              fontSize: 12.5,
              letterSpacing: 0.8,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            holderName,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 15,
              fontWeight: FontWeight.w700,
              letterSpacing: 1.0,
            ),
          ),
        ],
      ),
    );
  }
}