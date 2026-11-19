import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import '../../widgets/common.dart';

class TermsConditionsScreen extends StatelessWidget {
  const TermsConditionsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const ColoredBox(
              color: AppColors.navy,
              child: PageHeader(
                title: 'Terms & Conditions',
                subtitle: 'SUSWallet campus wallet usage rules',
                showBack: true,
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
              child: AppCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: const [
                    _SectionBlock(
                      heading: '1. University Account Eligibility',
                      body:
                      'SUSWallet is restricted to Sabancı University students and staff with an active @sabanciuniv.edu account linked to a valid SUCard.',
                    ),
                    SizedBox(height: 16),
                    _SectionBlock(
                      heading: '2. Main Wallet & Service Allocation',
                      body:
                      'Funds deposited into the Main Wallet remain unallocated until moved to a specific service wallet (Shuttle, Cafeteria, or Printing). Once allocated, funds are locked to that vendor and cannot be transferred to another service.',
                    ),
                    SizedBox(height: 16),
                    _SectionBlock(
                      heading: '3. Simulated Payments & Card Security',
                      body:
                      'Deposits and terminal taps in this release operate in simulation mode for campus wallet tracking. If your physical SUCard is lost or stolen, contact the SUCard Office immediately to freeze your card.',
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SectionBlock extends StatelessWidget {
  const _SectionBlock({required this.heading, required this.body});

  final String heading;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          heading,
          style: const TextStyle(
            color: AppColors.textPrimary,
            fontSize: 15.5,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          body,
          style: const TextStyle(
            color: AppColors.textMuted,
            fontSize: 13.5,
            height: 1.45,
          ),
        ),
      ],
    );
  }
}