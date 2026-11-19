import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_colors.dart';
import '../../data/suswallet_repository.dart';
import '../../state/session_controller.dart';
import '../../widgets/common.dart';
import '../../widgets/form_fields.dart';

class EditProfileScreen extends ConsumerStatefulWidget {
  const EditProfileScreen({super.key});

  @override
  ConsumerState<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends ConsumerState<EditProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _nameController;
  late final TextEditingController _phoneController;
  bool _saving = false;

  static final RegExp _phonePattern = RegExp(r'^\+?[0-9\s\-()]{7,20}$');

  @override
  void initState() {
    super.initState();
    final user = ref.read(sessionProvider).value;
    _nameController = TextEditingController(text: user?.fullName ?? '');
    _phoneController = TextEditingController(text: user?.phone ?? '');
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    super.dispose();
  }

  String? _validateFullName(String? value) {
    final trimmed = value?.trim() ?? '';
    if (trimmed.isEmpty) return 'Enter your full name.';
    if (trimmed.length < 2) return 'Full name must be at least 2 characters.';
    if (trimmed.length > 150) return 'Full name cannot exceed 150 characters.';
    return null;
  }

  String? _validatePhone(String? value) {
    final trimmed = value?.trim() ?? '';
    if (trimmed.isEmpty) return null; // Phone is optional on the backend.
    if (!_phonePattern.hasMatch(trimmed)) {
      return 'Enter a valid phone number (e.g. +90 532 123 45 12).';
    }
    return null;
  }

  Future<void> _handleSave() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text(
          'Save profile changes?',
          style: TextStyle(fontWeight: FontWeight.w700, fontSize: 18),
        ),
        content: Text(
          'Update your display name to "${_nameController.text.trim()}"'
              '${_phoneController.text.trim().isNotEmpty ? ' and phone to "${_phoneController.text.trim()}"' : ''}?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(minimumSize: const Size(104, 42)),
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Confirm'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    setState(() => _saving = true);
    try {
      final updated = await ref.read(repositoryProvider).updateProfile(
        fullName: _nameController.text,
        phone: _phoneController.text,
      );
      ref.read(sessionProvider.notifier).updateUser(updated);
      if (!mounted) return;
      showAppSnack(context, 'Profile updated successfully.');
      Navigator.of(context).pop();
    } on ApiException catch (error) {
      if (!mounted) return;
      showAppSnack(context, error.message, error: true);
    } catch (error) {
      if (!mounted) return;
      showAppSnack(context, describeError(error), error: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(sessionProvider).value;

    return Scaffold(
      backgroundColor: AppColors.background,
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            ColoredBox(
              color: AppColors.navy,
              child: PageHeader(
                title: 'Edit profile',
                showBack: true,
                bottomPadding: 26,
                bottom: Center(
                  child: Column(
                    children: [
                      Stack(
                        clipBehavior: Clip.none,
                        children: [
                          UserAvatar(user: user, size: 82),
                          Positioned(
                            right: -2,
                            bottom: -2,
                            child: Container(
                              width: 30,
                              height: 30,
                              decoration: BoxDecoration(
                                color: AppColors.primary,
                                shape: BoxShape.circle,
                                border: Border.all(
                                  color: AppColors.navy,
                                  width: 2,
                                ),
                              ),
                              child: const Icon(
                                Icons.camera_alt_outlined,
                                color: Colors.white,
                                size: 15,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      TextButton(
                        onPressed: () => showAppSnack(
                          context,
                          'Photo upload will be enabled with media picker.',
                        ),
                        child: const Text(
                          'Change photo',
                          style: TextStyle(color: Colors.white),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    AppCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const FieldLabel('Full name'),
                          TextFormField(
                            controller: _nameController,
                            textInputAction: TextInputAction.next,
                            validator: _validateFullName,
                            decoration: const InputDecoration(
                              hintText: 'Student Name',
                            ),
                          ),
                          const SizedBox(height: 16),
                          const FieldLabel('Phone'),
                          TextFormField(
                            controller: _phoneController,
                            keyboardType: TextInputType.phone,
                            textInputAction: TextInputAction.done,
                            validator: _validatePhone,
                            decoration: const InputDecoration(
                              hintText: '+90 532 123 45 12',
                            ),
                          ),
                          const SizedBox(height: 16),
                          _LockedField(
                            label: 'Student ID',
                            value: user?.studentId ?? '00000',
                          ),
                          const SizedBox(height: 16),
                          _LockedField(
                            label: 'SU username',
                            value: user?.suUsername ?? 'username',
                          ),
                          const SizedBox(height: 16),
                          _LockedField(
                            label: 'University email',
                            value: user?.email ?? 'name@sabanciuniv.edu',
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    const InfoNote(
                      'Locked fields are managed by the university.',
                    ),
                    const SizedBox(height: 20),
                    FilledButton(
                      onPressed: _saving ? null : _handleSave,
                      child: _saving
                          ? const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.4,
                          color: Colors.white,
                        ),
                      )
                          : const Text('Save changes'),
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

class _LockedField extends StatelessWidget {
  const _LockedField({
    required this.label,
    required this.value,
  });

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        FieldLabel(label),
        TextFormField(
          initialValue: value,
          enabled: false,
          style: const TextStyle(color: AppColors.textMuted),
          decoration: const InputDecoration(
            suffixIcon: Icon(
              Icons.lock_outline_rounded,
              size: 19,
              color: AppColors.textSubtle,
            ),
          ),
        ),
      ],
    );
  }
}