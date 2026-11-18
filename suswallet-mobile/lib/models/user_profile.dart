import '../core/api/api_config.dart';

/// Mirrors the backend `UserProfileResource`.
class UserProfile {
  const UserProfile({
    required this.id,
    required this.studentId,
    required this.suUsername,
    required this.email,
    required this.fullName,
    required this.initials,
    this.phone,
    this.maskedPhone,
    this.avatarPath,
    this.avatarUrl,
    this.isActive = true,
    this.sucardStatus = 'Active',
  });

  final String id;
  final String studentId;
  final String suUsername;
  final String email;
  final String fullName;
  final String initials;
  final String? phone;
  final String? maskedPhone;
  final String? avatarPath;
  final String? avatarUrl;
  final bool isActive;
  final String sucardStatus;

  /// Avatar URL that is reachable from this device (emulator, web, phone).
  String? get resolvedAvatarUrl =>
      ApiConfig.resolveMediaUrl(avatarPath, fallbackUrl: avatarUrl);

  factory UserProfile.fromJson(Map<String, dynamic> json) {
    final fullName = (json['full_name'] ?? '').toString();
    return UserProfile(
      id: json['id'].toString(),
      studentId: (json['student_id'] ?? '').toString(),
      suUsername: (json['su_username'] ?? '').toString(),
      email: (json['email'] ?? '').toString(),
      fullName: fullName,
      initials: (json['initials'] ?? _initialsOf(fullName)).toString(),
      phone: json['phone']?.toString(),
      maskedPhone: json['masked_phone']?.toString(),
      avatarPath: json['avatar_path']?.toString(),
      avatarUrl: json['avatar_url']?.toString(),
      isActive: json['is_active'] != false,
      sucardStatus: (json['sucard_status'] ?? 'Active').toString(),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'student_id': studentId,
        'su_username': suUsername,
        'email': email,
        'full_name': fullName,
        'initials': initials,
        'phone': phone,
        'masked_phone': maskedPhone,
        'avatar_path': avatarPath,
        'avatar_url': avatarUrl,
        'is_active': isActive,
        'sucard_status': sucardStatus,
      };

  static String _initialsOf(String name) {
    final parts =
        name.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
    if (parts.isEmpty) return 'SU';
    if (parts.length == 1) {
      return parts.first
          .substring(0, parts.first.length >= 2 ? 2 : 1)
          .toUpperCase();
    }
    return (parts.first[0] + parts.last[0]).toUpperCase();
  }
}
