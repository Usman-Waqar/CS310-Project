import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import '../../widgets/common.dart';

/// Empty tab page with only its navy title header.
class PlaceholderTab extends StatelessWidget {
  const PlaceholderTab({super.key, required this.title});

  final String title;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ColoredBox(
          color: AppColors.navy,
          child: PageHeader(title: title),
        ),
        const Expanded(child: SizedBox.shrink()),
      ],
    );
  }
}
