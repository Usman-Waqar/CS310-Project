import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Bottom navigation tabs, in the order shown in the wireframes.
enum ShellTab { home, services, transactions, settings }

final shellTabProvider = NotifierProvider<ShellTabController, ShellTab>(
  ShellTabController.new,
);

class ShellTabController extends Notifier<ShellTab> {
  @override
  ShellTab build() => ShellTab.home;

  void select(ShellTab tab) => state = tab;
}
