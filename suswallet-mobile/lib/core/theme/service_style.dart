import 'package:flutter/material.dart';

import 'app_colors.dart';

/// Visual identity (colour, icon, label) of each wallet / campus service.
class ServiceStyle {
  const ServiceStyle({
    required this.code,
    required this.label,
    required this.color,
    required this.icon,
  });

  final String code;
  final String label;
  final Color color;
  final IconData icon;

  /// Very light tint used behind icons in lists.
  Color get tint => color.withValues(alpha: 0.14);

  static const main = ServiceStyle(
    code: 'MAIN',
    label: 'Main Wallet',
    color: AppColors.navy,
    icon: Icons.credit_card_outlined,
  );

  static const shuttle = ServiceStyle(
    code: 'SHUTTLE',
    label: 'Shuttle',
    color: AppColors.shuttle,
    icon: Icons.directions_bus_outlined,
  );

  static const cafeteria = ServiceStyle(
    code: 'CAFETERIA',
    label: 'Cafeteria',
    color: AppColors.cafeteria,
    icon: Icons.restaurant,
  );

  static const printing = ServiceStyle(
    code: 'PRINTING',
    label: 'Printing',
    color: AppColors.printing,
    icon: Icons.print_outlined,
  );

  static const services = [shuttle, cafeteria, printing];

  static ServiceStyle of(String? code) {
    return switch (code?.toUpperCase()) {
      'SHUTTLE' => shuttle,
      'CAFETERIA' => cafeteria,
      'PRINTING' => printing,
      'MAIN' => main,
      _ => ServiceStyle(
          code: code ?? 'SERVICE',
          label: _titleCase(code ?? 'Service'),
          color: AppColors.primary,
          icon: Icons.storefront_outlined,
        ),
    };
  }

  static String _titleCase(String value) {
    if (value.isEmpty) return value;
    final lower = value.toLowerCase();
    return lower[0].toUpperCase() + lower.substring(1);
  }
}
