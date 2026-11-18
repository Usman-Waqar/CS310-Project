import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../data/suswallet_repository.dart';
import '../../widgets/common.dart';
import '../../widgets/form_fields.dart';

/// Password reset for pre-seeded accounts: the student proves ownership with
/// their university email + Student ID (`POST /auth/forgot-password`).
class ForgotPasswordScreen extends ConsumerStatefulWidget {
  const ForgotPasswordScreen({super.key, this.initialEmail = ''});

  final String initialEmail;

  @override
  ConsumerState<ForgotPasswordScreen> createState() =>
      _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends ConsumerState<ForgotPasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  late final _email = TextEditingController(text: widget.initialEmail);
  final _studentId = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();

  bool _submitting = false;
  Map<String, List<String>> _serverErrors = const {};

  @override
  void dispose() {
    _email.dispose();
    _studentId.dispose();
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  String? _serverError(String field) {
    final errors = _serverErrors[field];
    return (errors == null || errors.isEmpty) ? null : errors.first;
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    setState(() => _serverErrors = const {});
    if (!_formKey.currentState!.validate()) return;

    setState(() => _submitting = true);
    try {
      final message = await ref.read(repositoryProvider).resetPassword(
            email: _email.text.trim().toLowerCase(),
            studentId: _studentId.text.trim(),
            newPassword: _password.text,
          );
      if (!mounted) return;
      showAppSnack(context, message);
      Navigator.of(context).pop(_email.text.trim().toLowerCase());
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _serverErrors = error.fieldErrors);
      if (error.fieldErrors.isEmpty) {
        showAppSnack(context, error.message, error: true);
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SingleChildScrollView(
        child: OverlapHeader(
          overlap: 24,
          header: const PageHeader(
            title: 'Reset password',
            subtitle: 'Confirm your university account',
            showBack: true,
            bottomPadding: 16,
          ),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 32),
            child: AppCard(
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const InfoNote(
                      'Enter your university email and Student ID, then choose '
                      'a new password. You will be signed out on other devices.',
                    ),
                    const SizedBox(height: 20),
                    const FieldLabel('University email'),
                    TextFormField(
                      controller: _email,
                      keyboardType: TextInputType.emailAddress,
                      autocorrect: false,
                      textInputAction: TextInputAction.next,
                      validator: Validators.sabanciEmail,
                      decoration: InputDecoration(
                        hintText: 'name@sabanciuniv.edu',
                        prefixIcon: const Icon(Icons.mail_outline),
                        errorText: _serverError('email'),
                      ),
                    ),
                    const SizedBox(height: 16),
                    const FieldLabel('Student ID'),
                    TextFormField(
                      controller: _studentId,
                      keyboardType: TextInputType.number,
                      textInputAction: TextInputAction.next,
                      validator: (value) =>
                          Validators.required(value, 'Enter your Student ID.'),
                      decoration: InputDecoration(
                        hintText: '00012345',
                        prefixIcon: const Icon(Icons.badge_outlined),
                        errorText: _serverError('student_id'),
                      ),
                    ),
                    const SizedBox(height: 16),
                    const FieldLabel('New password'),
                    PasswordField(
                      controller: _password,
                      hint: 'At least 8 characters',
                      validator: Validators.newPassword,
                      errorText: _serverError('password'),
                    ),
                    const SizedBox(height: 16),
                    const FieldLabel('Confirm new password'),
                    PasswordField(
                      controller: _confirm,
                      hint: 'Repeat new password',
                      textInputAction: TextInputAction.done,
                      onSubmitted: (_) => _submit(),
                      validator: (value) => value != _password.text
                          ? 'Passwords do not match.'
                          : null,
                    ),
                    const SizedBox(height: 24),
                    FilledButton(
                      onPressed: _submitting ? null : _submit,
                      child: _submitting
                          ? const SizedBox(
                              width: 24,
                              height: 24,
                              child: CircularProgressIndicator(
                                color: Colors.white,
                                strokeWidth: 2.5,
                              ),
                            )
                          : const Text('Reset password'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
