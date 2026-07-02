/// Plain data models mirroring the API payloads (docs/MOBILE_API.md).
library;

class SchoolClass {
  final int id;
  final String name;
  final String? grade;
  final String? section;
  final int studentCount;

  const SchoolClass({
    required this.id,
    required this.name,
    this.grade,
    this.section,
    this.studentCount = 0,
  });

  factory SchoolClass.fromApi(Map<String, dynamic> j) => SchoolClass(
        id: j['id'] as int,
        name: (j['name'] ?? '') as String,
        grade: j['grade']?.toString(),
        section: j['section']?.toString(),
        studentCount: (j['student_count'] ?? 0) as int,
      );

  factory SchoolClass.fromDb(Map<String, dynamic> r) => SchoolClass(
        id: r['id'] as int,
        name: r['name'] as String,
        grade: r['grade'] as String?,
        section: r['section'] as String?,
        studentCount: (r['student_count'] ?? 0) as int,
      );

  Map<String, dynamic> toDb() => {
        'id': id,
        'name': name,
        'grade': grade,
        'section': section,
        'student_count': studentCount,
      };
}

class Student {
  final int id;
  final int classId;
  final String uid;
  final String fullName;
  final String? gender;
  final String? qrToken;
  final bool hasPhoto;

  const Student({
    required this.id,
    required this.classId,
    required this.uid,
    required this.fullName,
    this.gender,
    this.qrToken,
    this.hasPhoto = false,
  });

  factory Student.fromApi(Map<String, dynamic> j, int classId) => Student(
        id: j['id'] as int,
        classId: classId,
        uid: (j['uid'] ?? '') as String,
        fullName: (j['full_name'] ?? '') as String,
        gender: j['gender']?.toString(),
        qrToken: j['qr_token']?.toString(),
        hasPhoto: j['has_photo'] == true,
      );

  factory Student.fromDb(Map<String, dynamic> r) => Student(
        id: r['id'] as int,
        classId: r['class_id'] as int,
        uid: r['uid'] as String,
        fullName: r['full_name'] as String,
        gender: r['gender'] as String?,
        qrToken: r['qr_token'] as String?,
        hasPhoto: (r['has_photo'] ?? 0) == 1,
      );

  Map<String, dynamic> toDb() => {
        'id': id,
        'class_id': classId,
        'uid': uid,
        'full_name': fullName,
        'gender': gender,
        'qr_token': qrToken,
        'has_photo': hasPhoto ? 1 : 0,
      };
}

/// One period row of the teacher's schedule (today or weekly).
class PeriodSlot {
  final int periodId;
  final String label;
  final String startTime;
  final String endTime;
  final bool isBreak;
  final String? className;
  final int? classId;
  final String? subjectName;
  final String? room;

  const PeriodSlot({
    required this.periodId,
    required this.label,
    required this.startTime,
    required this.endTime,
    required this.isBreak,
    this.className,
    this.classId,
    this.subjectName,
    this.room,
  });

  factory PeriodSlot.fromApi(Map<String, dynamic> j) {
    final entry = j['entry'] as Map<String, dynamic>?;
    return PeriodSlot(
      periodId: j['period_id'] as int,
      label: (j['label'] ?? '') as String,
      startTime: (j['start_time'] ?? '') as String,
      endTime: (j['end_time'] ?? '') as String,
      isBreak: j['is_break'] == true,
      className: entry?['class_name']?.toString(),
      classId: entry?['class_id'] as int?,
      subjectName: entry?['subject_name']?.toString(),
      room: entry?['room']?.toString(),
    );
  }
}

/// Attendance statuses — PRD's P/A/L/E.
enum AttendanceStatus { present, absent, late, excused }

extension AttendanceStatusX on AttendanceStatus {
  String get apiValue => name; // present | absent | late | excused
  String get letter => switch (this) {
        AttendanceStatus.present => 'P',
        AttendanceStatus.absent => 'A',
        AttendanceStatus.late => 'L',
        AttendanceStatus.excused => 'E',
      };
}
