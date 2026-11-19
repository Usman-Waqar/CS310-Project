import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/theme/app_colors.dart';
import '../../state/shell_tab.dart';
import '../home/home_screen.dart';
import '../services/services_screen.dart';
import '../settings/about_suswallet_screen.dart';
import '../settings/profile_screen.dart';
import '../settings/settings_screen.dart';
import '../settings/sucard_security_screen.dart';
import '../settings/terms_conditions_screen.dart';
import '../transactions/transactions_screen.dart';

/// Hosts the four bottom-navigation tabs.
class MainShell extends ConsumerWidget {
  const MainShell({super.key});

  void _openPage(BuildContext context, Widget page) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => page),
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final tab = ref.watch(shellTabProvider);

    return PopScope(
      // Back from any other tab returns to Home before leaving the app.
      canPop: tab == ShellTab.home,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) ref.read(shellTabProvider.notifier).select(ShellTab.home);
      },
      child: Scaffold(
        body: IndexedStack(
          index: tab.index,
          children: [
            const HomeScreen(),
            const ServicesScreen(),
            const TransactionsScreen(),
            SettingsScreen(
              onOpenProfile: () => _openPage(context, const ProfileScreen()),
              onOpenSecurity: () =>
                  _openPage(context, const SUCardSecurityScreen()),
              onOpenTerms: () =>
                  _openPage(context, const TermsConditionsScreen()),
              onOpenAbout: () =>
                  _openPage(context, const AboutSusWalletScreen()),
            ),
          ],
        ),
        bottomNavigationBar: _BottomBar(
          current: tab,
          onSelect: ref.read(shellTabProvider.notifier).select,
        ),
      ),
    );
  }
}

class _BottomBar extends StatelessWidget {
  const _BottomBar({required this.current, required this.onSelect});

  final ShellTab current;
  final ValueChanged<ShellTab> onSelect;

  static const _items = [
    (ShellTab.home, Icons.home_outlined, Icons.home_rounded, 'Home'),
    (
      ShellTab.services,
      Icons.credit_card_outlined,
      Icons.credit_card,
      'Services',
    ),
    (ShellTab.transactions, Icons.repeat, Icons.repeat, 'Transactions'),
    (ShellTab.settings, Icons.settings_outlined, Icons.settings, 'Settings'),
  ];

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(color: Color(0x120A2467), blurRadius: 12),
        ],
      ),
      child: SafeArea(
        top: false,
        child: SizedBox(
          height: 68,
          child: Row(
            children: [
              for (final (tab, icon, activeIcon, label) in _items)
                Expanded(
                  child: _BottomBarItem(
                    icon: current == tab ? activeIcon : icon,
                    label: label,
                    selected: current == tab,
                    onTap: () => onSelect(tab),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _BottomBarItem extends StatelessWidget {
  const _BottomBarItem({
    required this.icon,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = selected ? AppColors.primary : AppColors.textMuted;
    return Semantics(
      selected: selected,
      button: true,
      label: label,
      child: InkResponse(
        onTap: onTap,
        radius: 36,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: color, size: 27),
            const SizedBox(height: 4),
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.fade,
              softWrap: false,
              style: TextStyle(
                color: color,
                fontSize: 12.5,
                fontWeight: selected ? FontWeight.w600 : FontWeight.w400,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
