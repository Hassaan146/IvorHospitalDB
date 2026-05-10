IF DB_ID('IvorHospitalDB') IS NULL
BEGIN
    CREATE DATABASE IvorHospitalDB;
END
GO

USE IvorHospitalDB;
GO


IF OBJECT_ID('TRG_Validate_Bed_Ward','TR') IS NOT NULL DROP TRIGGER TRG_Validate_Bed_Ward;
GO
IF OBJECT_ID('TRG_Check_Ward_Sisters', 'TR') IS NOT NULL DROP TRIGGER TRG_Check_Ward_Sisters;
GO
IF OBJECT_ID('TRG_Check_CareUnit_InCharge','TR') IS NOT NULL DROP TRIGGER TRG_Check_CareUnit_InCharge;
GO

IF OBJECT_ID('PERFORMANCE_RECORD', 'U') IS NOT NULL DROP TABLE PERFORMANCE_RECORD;
IF OBJECT_ID('EXPERIENCE_RECORD',  'U') IS NOT NULL DROP TABLE EXPERIENCE_RECORD;
IF OBJECT_ID('MEDICAL_HISTORY',    'U') IS NOT NULL DROP TABLE MEDICAL_HISTORY;
IF OBJECT_ID('PATIENT',            'U') IS NOT NULL DROP TABLE PATIENT;
IF OBJECT_ID('BED',                'U') IS NOT NULL DROP TABLE BED;
IF OBJECT_ID('CARE_UNIT',          'U') IS NOT NULL DROP TABLE CARE_UNIT;
IF OBJECT_ID('NURSE',              'U') IS NOT NULL DROP TABLE NURSE;
IF OBJECT_ID('WARD',               'U') IS NOT NULL DROP TABLE WARD;
IF OBJECT_ID('CONSULTANT',         'U') IS NOT NULL DROP TABLE CONSULTANT;
IF OBJECT_ID('DOCTOR',             'U') IS NOT NULL DROP TABLE DOCTOR;
IF OBJECT_ID('COMPLAINT',          'U') IS NOT NULL DROP TABLE COMPLAINT;
IF OBJECT_ID('TREATMENT',          'U') IS NOT NULL DROP TABLE TREATMENT;
IF OBJECT_ID('STAFF',              'U') IS NOT NULL DROP TABLE STAFF;
GO

create table staff (
    staffno int not null,
    name varchar (100) not null,
    stafftype varchar(10) not null,
    constraint pk_staff primary key (staffno),
    constraint ck_stafftype check (stafftype in ('doctor', 'nurse'))
);
go

create table ward (
    wardname varchar(50)  not null,
    specialty varchar(100) not null,
    daysisterstaffno int null,
    nightsisterstaffno int null,
    constraint pk_ward primary key (wardname)
);
go


create table care_unit (
    careunitno int not null,
    wardname varchar(50) not null,
    inchargestaffno int null,
    constraint pk_careunit primary key (careunitno),
    constraint fk_careunit_ward foreign key (wardname) references ward(wardname)
);
go


create table doctor (
    staffno int not null,
    position varchar(50) not null,
    consultantno int null,
    datejoinedteam date null,
    constraint pk_doctor primary key (staffno),
    constraint fk_doctor_staff foreign key (staffno) references staff(staffno),
    constraint ck_doctor_position check (
        position in ('student', 'junior houseman', 'senior houseman',
                     'assistant registrar', 'registrar')
    )
);
go


create table consultant (
    staffno int not null,
    specialty varchar(100) not null,
    constraint pk_consultant primary key (staffno),
    constraint fk_consultant_doctor foreign key (staffno) references doctor(staffno)
);
go


create table nurse (
    staffno int not null,
    nursetype varchar(50) not null,
    wardname varchar(50) not null,
    careunitno int null,
    constraint pk_nurse primary key (staffno),
    constraint fk_nurse_staff foreign key (staffno) references staff(staffno),
    constraint fk_nurse_ward foreign key (wardname) references ward(wardname),
    constraint fk_nurse_careunit foreign key (careunitno) references care_unit(careunitno),
    constraint ck_nursetype check (
        nursetype in ('day sister', 'night sister', 'staff nurse', 'non-registered nurse')
    )
);
go


create table bed (
    bedno int not null,
    wardname varchar(50) not null,
    constraint pk_bed primary key (bedno),
    constraint fk_bed_ward foreign key (wardname) references ward(wardname)
);
go


create table patient (
    patientno int not null,
    name varchar(100) not null,
    dateofbirth  date not null,
    dateadmitted date not null,
    datedischarged date  null,
    careunitno int not null,
    bedno int not null,
    doctorincharge int not null,
    constraint pk_patient primary key (patientno),
    constraint ck_admit_discharge  check (datedischarged is null or datedischarged >= dateadmitted),
    constraint fk_patient_careunit foreign key (careunitno) references care_unit(careunitno),
    constraint fk_patient_bed foreign key (bedno) references bed(bedno),
    constraint fk_patient_doctor   foreign key (doctorincharge) references doctor(staffno)
);
go


create table complaint (
    complaintcode int not null,
    description varchar(255) not null,
    constraint pk_complaint primary key (complaintcode)
);
go


create table treatment (
    treatmentcode int          not null,
    description   varchar(255) not null,
    constraint pk_treatment primary key (treatmentcode)
);
go


create table medical_history (
    historyid int identity(1,1) not null,
    patientno int  not null,
    complaintcode int  not null,
    treatmentcode int  not null,
    doctorno int  not null,
    datestarted date not null,
    dateended date null,
    constraint pk_medicalhistory primary key (historyid),
    constraint ck_treatment_dates check (dateended is null or dateended >= datestarted),
    constraint fk_mh_patient foreign key (patientno) references patient(patientno),
    constraint fk_mh_complaint foreign key (complaintcode) references complaint(complaintcode),
    constraint fk_mh_treatment foreign key (treatmentcode) references treatment(treatmentcode),
    constraint fk_mh_doctor foreign key (doctorno) references doctor(staffno),
    constraint uq_mh_patient_complaint_date unique (patientno, complaintcode, datestarted)
);
go


create table performance_record (
    recordid int identity(1,1) not null,
    doctorno int not null,
    consultantno int not null,
    gradedate date not null,
    grade varchar(10) not null,
    constraint pk_performancerecord primary key (recordid),
    constraint fk_pr_doctor foreign key (doctorno) references doctor(staffno),
    constraint fk_pr_consultant foreign key (consultantno) references consultant(staffno)
);
go


create table experience_record (
    expid         int identity(1,1) not null,
    doctorno      int          not null,
    fromdate      date         not null,
    todate        date         null,
    position      varchar(50)  not null,
    establishment varchar(100) not null,
    constraint pk_experiencerecord primary key (expid),
    constraint fk_er_doctor         foreign key (doctorno) references doctor(staffno)
);
go


alter table doctor
    add constraint fk_doctor_consultant
    foreign key (consultantno) references consultant(staffno);
go

alter table ward
    add constraint fk_ward_daysister
    foreign key (daysisterstaffno) references nurse(staffno);
go

alter table ward
    add constraint fk_ward_nightsister
    foreign key (nightsisterstaffno) references nurse(staffno);
go

alter table care_unit
    add constraint fk_careunit_incharge
    foreign key (inchargestaffno) references nurse(staffno);
go


-- Ensures a patient's bed belongs to the same ward as their care unit
create trigger trg_validate_bed_ward
on patient
after insert, update
as
begin
    set nocount on;
    if exists (
        select 1
        from inserted i
        join care_unit cu on cu.careunitno = i.careunitno
        join bed b        on b.bedno       = i.bedno
        where cu.wardname <> b.wardname
    )
    begin
        raiserror('bed must belong to the same ward as the patient''s care unit.', 16, 1);
        rollback transaction;
    end
end;
go

-- ensures day/night sister assignments match the correct nursetype
create trigger trg_check_ward_sisters
on ward
after insert, update
as
begin
    set nocount on;
    if exists (
        select 1 from inserted i
        join nurse n on n.staffno = i.daysisterstaffno
        where i.daysisterstaffno is not null and n.nursetype <> 'day sister'
    )
    begin
        raiserror('day sister must be a nurse with nursetype = day sister.', 16, 1);
        rollback transaction;
        return;
    end
    if exists (
        select 1 from inserted i
        join nurse n on n.staffno = i.nightsisterstaffno
        where i.nightsisterstaffno is not null and n.nursetype <> 'night sister'
    )
    begin
        raiserror('night sister must be a nurse with nursetype = night sister.', 16, 1);
        rollback transaction;
        return;
    end
end;
go

-- ensures only a staff nurse can be placed in charge of a care unit
create trigger trg_check_careunit_incharge
on care_unit
after insert, update
as
begin
    set nocount on;
    if exists (
        select 1 from inserted i
        join nurse n on n.staffno = i.inchargestaffno
        where n.nursetype <> 'staff nurse'
    )
    begin
        raiserror('care unit in-charge must be a staff nurse.', 16, 1);
        rollback transaction;
    end
end;
go


-- at any given time a patient may have only one active treatment per complaint
create unique index ux_medicalhistory_activetreatment
on medical_history (patientno, complaintcode)
where dateended is null;
go

-- a doctor can only receive one performance grade per date
create unique index ux_performance_doctor_gradedate
on performance_record (doctorno, gradedate);
go


insert into staff (staffno, name, stafftype) values
(101, 'Dr. Ahmed Raza',        'Doctor'),
(102, 'Dr. Ayesha Khan',       'Doctor'),
(103, 'Dr. Bilal Hussain',     'Doctor'),
(104, 'Dr. Fatima Noor',       'Doctor'),
(105, 'Dr. Usman Ali',         'Doctor'),
(106, 'Dr. Hira Malik',        'Doctor'),
(107, 'Dr. Saad Iqbal',        'Doctor'),
(108, 'Dr. Maryam Shah',       'Doctor'),
(109, 'Dr. Hassan Tariq',      'Doctor'),
(110, 'Dr. Zara Ahmed',        'Doctor'),
(111, 'Dr. Omar Farooq',       'Doctor'),
(112, 'Dr. Sana Javed',        'Doctor'),
(113, 'Dr. Abdullah Siddiqui', 'Doctor'),
(114, 'Dr. Alina Riaz',        'Doctor'),
(115, 'Dr. Hamza Qureshi',     'Doctor'),
(116, 'Dr. Mahnoor Sheikh',    'Doctor'),

(201, 'Nurse Rabia Ahmed',     'Nurse'),
(202, 'Nurse Hina Khalid',     'Nurse'),
(203, 'Nurse Saba Tariq',      'Nurse'),
(204, 'Nurse Nida Farooq',     'Nurse'),
(205, 'Nurse Areeba Khan',     'Nurse'),
(206, 'Nurse Zainab Ali',      'Nurse'),
(207, 'Nurse Sidra Hussain',   'Nurse'),
(208, 'Nurse Anum Malik',      'Nurse'),
(209, 'Nurse Maria Shah',      'Nurse'),
(210, 'Nurse Laiba Iqbal',     'Nurse'),
(211, 'Nurse Samina Raza',     'Nurse'),
(212, 'Nurse Hira Usman',      'Nurse'),
(213, 'Nurse Noor Fatima',     'Nurse'),
(214, 'Nurse Komal Javed',     'Nurse'),
(215, 'Nurse Mehak Qureshi',   'Nurse'),
(216, 'Nurse Aiman Sheikh',    'Nurse'),
(217, 'Nurse Amna Saeed',      'Nurse');
go

--  ward (sisters null for now set after nurse is loaded) 
insert into ward (wardname, specialty) values
('WARD A', 'ORTHOPEDICS'),
('WARD B', 'GERIATRICS'),
('WARD C', 'CARDIOLOGY'),
('WARD D', 'NEUROLOGY');
go

-- care_unit (incharge null for now set after nurse) 
insert into care_unit (careunitno, wardname, inchargestaffno) values
(1, 'WARD A', NULL),
(2, 'WARD A', NULL),
(3, 'WARD B', NULL),
(4, 'WARD B', NULL),
(5, 'WARD C', NULL),
(6, 'WARD C', NULL),
(7, 'WARD D', NULL),
(8, 'WARD D', NULL);
go

-- doctor (consultantno null for now set after consultant) 
insert into doctor (staffno, position, consultantno, datejoinedteam) values
(101, 'Registrar',           NULL, NULL),
(102, 'Registrar',           NULL, NULL),
(103, 'Junior Houseman',     NULL, NULL),
(104, 'Senior Houseman',     NULL, NULL),
(105, 'Assistant Registrar', NULL, NULL),
(106, 'Junior Houseman',     NULL, NULL),
(107, 'Senior Houseman',     NULL, NULL),
(108, 'Student',             NULL, NULL),
(109, 'Assistant Registrar', NULL, NULL),
(110, 'Junior Houseman',     NULL, NULL),
(111, 'Registrar',           NULL, NULL),
(112, 'Registrar',           NULL, NULL),
(113, 'Junior Houseman',     NULL, NULL),
(114, 'Senior Houseman',     NULL, NULL),
(115, 'Assistant Registrar', NULL, NULL),
(116, 'Junior Houseman',     NULL, NULL);
go

-- -- consultant -----------------------------------------------
insert into consultant (staffno, specialty) values
(101, 'ORTHOPEDICS'),
(102, 'GERIATRICS'),
(111, 'CARDIOLOGY'),
(112, 'NEUROLOGY');
go

-- -- NURSE ----------------------------------------------------
INSERT INTO NURSE (StaffNo, NurseType, WardName, CareUnitNo) VALUES
(201, 'Day Sister',           'Ward A', NULL),
(202, 'Night Sister',         'Ward A', NULL),
(203, 'Staff Nurse',          'Ward A', 1),
(204, 'Staff Nurse',          'Ward A', 2),
(205, 'Day Sister',           'Ward B', NULL),
(206, 'Night Sister',         'Ward B', NULL),
(207, 'Staff Nurse',          'Ward B', 3),
(208, 'Non-Registered Nurse', 'Ward B', NULL),
(217, 'Staff Nurse',          'Ward B', 4),
(209, 'Day Sister',           'Ward C', NULL),
(210, 'Night Sister',         'Ward C', NULL),
(211, 'Staff Nurse',          'Ward C', 5),
(212, 'Staff Nurse',          'Ward C', 6),
(213, 'Day Sister',           'Ward D', NULL),
(214, 'Night Sister',         'Ward D', NULL),
(215, 'Staff Nurse',          'Ward D', 7),
(216, 'Staff Nurse',          'Ward D', 8);
GO

-- update ward sisters 
update ward set daysisterstaffno = 201, nightsisterstaffno = 202 where wardname = 'ward a';
update ward set daysisterstaffno = 205, nightsisterstaffno = 206 where wardname = 'ward b';
update ward set daysisterstaffno = 209, nightsisterstaffno = 210 where wardname = 'ward c';
update ward set daysisterstaffno = 213, nightsisterstaffno = 214 where wardname = 'ward d';
go

-- update care_unit in-charge 
update care_unit set inchargestaffno = 203 where careunitno = 1;
update care_unit set inchargestaffno = 204 where careunitno = 2;
update care_unit set inchargestaffno = 207 where careunitno = 3;
update care_unit set inchargestaffno = 217 where careunitno = 4;
update care_unit set inchargestaffno = 211 where careunitno = 5;
update care_unit set inchargestaffno = 212 where careunitno = 6;
update care_unit set inchargestaffno = 215 where careunitno = 7;
update care_unit set inchargestaffno = 216 where careunitno = 8;
go

-- Update DOCTOR consultant links 
update doctor set consultantno = 101, datejoinedteam = '2023-01-15' where staffno = 103;
update doctor set consultantno = 101, datejoinedteam = '2022-06-01' where staffno = 104;
update doctor set consultantno = 101, datejoinedteam = '2023-09-01' where staffno = 105;
update doctor set consultantno = 102, datejoinedteam = '2024-01-10' where staffno = 106;
update doctor set consultantno = 102, datejoinedteam = '2023-03-20' where staffno = 107;
update doctor set consultantno = 102, datejoinedteam = '2024-07-01' where staffno = 108;
update doctor set consultantno = 101, datejoinedteam = '2022-11-15' where staffno = 109;
update doctor set consultantno = 102, datejoinedteam = '2023-05-01' where staffno = 110;
update doctor set consultantno = 111, datejoinedteam = '2024-03-01' where staffno = 113;
update doctor set consultantno = 111, datejoinedteam = '2024-06-15' where staffno = 114;
update doctor set consultantno = 112, datejoinedteam = '2025-01-10' where staffno = 115;
update doctor set consultantno = 112, datejoinedteam = '2025-02-20' where staffno = 116;
go

--  bed 
insert into bed (bedno, wardname) values
( 1, 'ward a'), ( 2, 'ward a'), ( 3, 'ward a'), ( 4, 'ward a'),
(13, 'ward a'), (14, 'ward a'), (15, 'ward a'), (16, 'ward a'),
( 5, 'ward b'), ( 6, 'ward b'), ( 7, 'ward b'), ( 8, 'ward b'),
(17, 'ward b'), (18, 'ward b'), (19, 'ward b'), (20, 'ward b'),
( 9, 'ward c'), (10, 'ward c'), (21, 'ward c'), (22, 'ward c'),
(23, 'ward c'), (24, 'ward c'), (25, 'ward c'), (26, 'ward c'),
(11, 'ward d'), (12, 'ward d'), (27, 'ward d'), (28, 'ward d'),
(29, 'ward d'), (30, 'ward d');
go


-- patient 
insert into patient (
    patientno, name, dateofbirth, dateadmitted, datedischarged,
    careunitno, bedno, doctorincharge
) values

-- Ward A
(1001, 'Ayesha Khan',       '1952-03-14', '2026-01-10', NULL,         1,  1, 103),
(1002, 'Muhammad Usman',    '1948-07-22', '2026-01-15', NULL,         2,  2, 104),
(1005, 'Fatima Zahra',      '1980-01-17', '2026-02-20', NULL,         1,  3, 103),
(1006, 'Ali Raza',          '1955-04-25', '2026-03-05', NULL,         2,  4, 105),
(1009, 'Hassan Ali',        '1960-05-15', '2026-01-20', NULL,         1, 13, 109),
(1010, 'Zainab Malik',      '1975-08-22', '2026-01-25', '2026-02-28', 2, 14, 104),
(1011, 'Omar Farooq',       '1958-03-10', '2026-02-05', NULL,         1, 15, 105),
(1012, 'Sana Javed',        '1982-11-28', '2026-02-12', NULL,         2, 16, 103),

-- Ward B
(1003, 'Bilal Hussain',     '1970-11-05', '2026-02-01', '2026-03-10', 3,  5, 106),
(1004, 'Amina Sheikh',      '1965-08-30', '2026-02-10', NULL,         4,  6, 107),
(1007, 'Rabia Ahmed',       '1942-09-12', '2026-03-15', NULL,         3,  7, 106),
(1008, 'Saad Iqbal',        '1938-12-01', '2026-03-20', NULL,         4,  8, 110),
(1013, 'Imran Shah',        '1940-06-18', '2026-01-28', NULL,         3, 17, 107),
(1014, 'Hira Qureshi',      '1943-09-04', '2026-02-08', '2026-03-20', 4, 18, 110),
(1015, 'Hamza Tariq',       '1937-12-22', '2026-02-18', NULL,         3, 19, 106),
(1016, 'Mahnoor Khan',      '1945-03-15', '2026-03-01', NULL,         4, 20, 107),

-- Ward C
(1017, 'Asad Ali',          '1963-07-30', '2026-01-15', NULL,         5,  9, 113),
(1018, 'Maryam Noor',       '1970-02-14', '2026-01-22', NULL,         6, 10, 114),
(1019, 'Fahad Ahmed',       '1955-04-08', '2026-02-03', '2026-03-05', 5, 21, 113),
(1020, 'Areeba Khan',       '1968-10-19', '2026-02-14', NULL,         6, 22, 114),
(1021, 'Saifullah Malik',   '1951-08-25', '2026-02-25', NULL,         5, 23, 113),
(1022, 'Komal Raza',        '1959-01-11', '2026-03-08', NULL,         6, 24, 114),
(1023, 'Noman Siddiqui',    '1972-06-03', '2026-03-18', NULL,         5, 25, 113),
(1024, 'Laiba Hassan',      '1964-09-27', '2026-03-25', NULL,         6, 26, 114),

-- Ward D
(1025, 'Shahid Afridi',     '1948-02-16', '2026-01-18', '2026-03-01', 7, 11, 115),
(1026, 'Bushra Bibi',       '1953-07-09', '2026-01-26', NULL,         8, 12, 116),
(1027, 'Kamran Akmal',      '1961-04-22', '2026-02-06', '2026-03-15', 7, 27, 115),
(1028, 'Nadia Hussain',     '1966-11-30', '2026-02-16', NULL,         8, 28, 116),
(1029, 'Yasir Shah',        '1944-08-13', '2026-03-10', NULL,         7, 29, 115),
(1030, 'Sadia Mirza',       '1957-05-07', '2026-03-22', NULL,         8, 30, 116);
GO

-- complaint 
insert into complaint (complaintcode, description) values
(10, 'Fractured Femur'),
(11, 'Hip Replacement'),
(12, 'Dementia'),
(13, 'Osteoporosis'),
(14, 'Heart Failure'),
(15, 'Stroke'),
(16, 'Knee Injury'),
(17, 'Arrhythmia'),
(18, 'Hypertension'),
(19, 'Parkinson''s Disease'),
(20, 'Epilepsy'),
(21, 'Coronary Artery Disease'),
(22, 'Appendicitis'),
(23, 'Diabetes Mellitus'),
(24, 'Chronic Back Pain'),
(25, 'Pneumonia'),
(26, 'Kidney Stones');
go

-- treatment 
insert into treatment (treatmentcode, description) values
(20, 'Physiotherapy'),
(21, 'Surgery'),
(22, 'Medication'),
(23, 'Chemotherapy'),
(24, 'Radiotherapy'),
(25, 'Occupational Therapy'),
(26, 'Hydrotherapy'),
(27, 'Cardiac Rehabilitation'),
(28, 'Neurological Therapy'),
(29, 'Electrotherapy'),
(30, 'Dietary Management'),
(31, 'Pain Management'),
(32, 'Respiratory Therapy'),
(33, 'Dialysis'),
(34, 'Cognitive Behavioural Therapy');
GO

--  medical_history 
insert into medical_history
    (patientno, complaintcode, treatmentcode, doctorno, datestarted, dateended)
values
-- Ward A patients
(1001, 10, 21, 103, '2026-01-11', '2026-01-20'),
(1001, 10, 20, 103, '2026-01-21', NULL),
(1001, 16, 26, 104, '2026-01-25', NULL),
(1002, 11, 21, 104, '2026-01-16', '2026-02-01'),
(1002, 11, 20, 104, '2026-02-02', NULL),
(1005, 13, 20, 103, '2026-02-21', NULL),
(1005, 16, 26, 105, '2026-02-22', NULL),
(1006, 11, 21, 105, '2026-03-06', NULL),
(1009, 10, 21, 109, '2026-01-21', '2026-01-30'),
(1009, 10, 20, 109, '2026-01-31', NULL),
(1010, 16, 26, 104, '2026-01-26', '2026-02-20'),
(1011, 11, 21, 105, '2026-02-06', '2026-02-19'),
(1011, 11, 20, 105, '2026-02-20', NULL),
(1012, 13, 20, 103, '2026-02-13', NULL),
(1012, 16, 26, 103, '2026-02-15', NULL),
-- Ward B patients
(1003, 14, 22, 106, '2026-02-01', '2026-03-01'),
(1004, 15, 22, 107, '2026-02-10', '2026-02-14'),
(1004, 15, 25, 107, '2026-02-15', NULL),
(1007, 12, 25, 106, '2026-03-16', NULL),
(1008, 12, 22, 110, '2026-03-21', NULL),
(1008, 14, 22, 110, '2026-03-22', NULL),
(1013, 12, 25, 107, '2026-01-29', NULL),
(1013, 13, 20, 107, '2026-02-01', NULL),
(1014, 13, 20, 110, '2026-02-09', '2026-03-10'),
(1015, 12, 22, 106, '2026-02-19', NULL),
(1016, 15, 22, 107, '2026-03-02', '2026-03-09'),
(1016, 15, 25, 107, '2026-03-10', NULL),
-- Ward C patients
(1017, 17, 22, 113, '2026-01-16', '2026-01-19'),
(1017, 17, 27, 113, '2026-01-20', NULL),
(1018, 18, 22, 114, '2026-01-23', NULL),
(1019, 14, 22, 113, '2026-02-04', '2026-03-01'),
(1020, 21, 21, 114, '2026-02-15', '2026-02-28'),
(1020, 21, 27, 114, '2026-03-01', NULL),
(1021, 17, 22, 113, '2026-02-26', NULL),
(1022, 18, 22, 114, '2026-03-09', NULL),
(1023, 14, 22, 113, '2026-03-19', NULL),
(1024, 21, 22, 114, '2026-03-26', NULL),
-- Ward D patients
(1025, 15, 22, 115, '2026-01-19', '2026-02-15'),
(1025, 15, 28, 115, '2026-02-16', NULL),
(1026, 19, 28, 116, '2026-01-27', '2026-01-31'),
(1026, 19, 25, 116, '2026-02-01', NULL),
(1027, 20, 22, 115, '2026-02-07', '2026-03-10'),
(1028, 15, 22, 116, '2026-02-17', '2026-02-24'),
(1028, 15, 28, 116, '2026-02-25', NULL),
(1029, 19, 28, 115, '2026-03-11', NULL),
(1030, 20, 29, 116, '2026-03-23', NULL);
go

--  performance_record 
insert into performance_record (doctorno, consultantno, gradedate, grade) values
-- Consultant 101 (Orthopedics) team
(103, 101, '2023-06-30', 'B+'),
(103, 101, '2023-12-31', 'A'),
(104, 101, '2023-06-30', 'B'),
(104, 101, '2023-12-31', 'A-'),
(105, 101, '2024-03-31', 'B+'),
(105, 101, '2024-09-30', 'A'),
(109, 101, '2023-05-31', 'A-'),
(109, 101, '2023-11-30', 'A'),
-- Consultant 102 (Geriatrics) team
(106, 102, '2024-06-30', 'C+'),
(106, 102, '2024-12-31', 'B'),
(107, 102, '2023-09-30', 'A'),
(107, 102, '2024-03-31', 'A-'),
(108, 102, '2025-01-31', 'B'),
(110, 102, '2023-11-30', 'B+'),
(110, 102, '2024-05-31', 'A-'),
-- Consultant 111 (Cardiology) team
(113, 111, '2024-09-30', 'B+'),
(114, 111, '2024-12-31', 'B'),
-- Consultant 112 (Neurology) team
(115, 112, '2025-07-31', 'A'),
(116, 112, '2025-08-31', 'B+');
GO


insert into experience_record 
(doctorno, fromdate, todate, position, establishment) values

(103, '2018-08-01', '2020-07-31', 'Student', 'Jinnah Hospital Lahore'),
(103, '2020-08-01', '2022-12-31', 'Junior Houseman', 'Services Hospital Lahore'),
(103, '2023-01-01', NULL, 'Junior Houseman', 'Aga Khan University Hospital'),

(104, '2016-09-01', '2019-08-31', 'Student', 'Dow University Hospital Karachi'),
(104, '2019-09-01', '2022-05-31', 'Junior Houseman', 'Shifa International Hospital Islamabad'),
(104, '2022-06-01', NULL, 'Senior Houseman', 'PIMS Islamabad'),

(105, '2014-01-01', '2017-12-31', 'Junior Houseman', 'CMH Rawalpindi'),
(105, '2018-01-01', '2022-10-31', 'Senior Houseman', 'Lady Reading Hospital Peshawar'),
(105, '2022-11-01', NULL, 'Assistant Registrar', 'Aga Khan Hospital Karachi'),

(106, '2020-07-01', '2023-12-31', 'Student', 'King Edward Medical University'),
(106, '2024-01-01', NULL, 'Junior Houseman', 'Mayo Hospital Lahore'),

(107, '2017-03-01', '2020-02-28', 'Junior Houseman', 'Jinnah Hospital Karachi'),
(107, '2020-03-01', '2023-02-28', 'Senior Houseman', 'Civil Hospital Karachi'),
(107, '2023-03-01', NULL, 'Senior Houseman', 'Shaukat Khanum Hospital'),

(108, '2021-06-01', NULL, 'Student', 'Fatima Jinnah Medical University'),

(109, '2015-01-01', '2018-12-31', 'Junior Houseman', 'Nishtar Hospital Multan'),
(109, '2019-01-01', '2022-10-31', 'Senior Houseman', 'CMH Lahore'),
(109, '2022-11-01', NULL, 'Assistant Registrar', 'PIMS Islamabad'),

(110, '2019-09-01', '2023-04-30', 'Student', 'Dow Medical College Karachi'),
(110, '2023-05-01', NULL, 'Junior Houseman', 'Aga Khan Hospital Karachi'),

(101, '2005-08-01', '2010-07-31', 'Junior Houseman', 'King Edward Medical University'),
(101, '2010-08-01', '2015-06-30', 'Senior Houseman', 'Jinnah Hospital Lahore'),
(101, '2015-07-01', '2019-12-31', 'Assistant Registrar', 'Shifa International Hospital'),
(101, '2020-01-01', NULL, 'Registrar', 'Aga Khan Hospital Karachi'),

(102, '2007-09-01', '2012-08-31', 'Junior Houseman', 'Dow Medical College Karachi'),
(102, '2012-09-01', '2017-12-31', 'Senior Houseman', 'Services Hospital Lahore'),
(102, '2018-01-01', '2021-06-30', 'Assistant Registrar', 'PIMS Islamabad'),
(102, '2021-07-01', NULL, 'Registrar', 'Shifa International Hospital'),

(111, '2006-01-01', '2010-12-31', 'Junior Houseman', 'Punjab Institute of Cardiology'),
(111, '2011-01-01', '2016-12-31', 'Senior Houseman', 'Aga Khan Hospital Karachi'),
(111, '2017-01-01', '2022-12-31', 'Assistant Registrar', 'NICVD Karachi'),
(111, '2023-01-01', NULL, 'Registrar', 'PIMS Islamabad'),

(113, '2018-01-01', '2022-05-31', 'Student', 'Aga Khan Medical College'),
(113, '2022-06-01', '2024-02-28', 'Junior Houseman', 'Civil Hospital Karachi'),
(113, '2024-03-01', NULL, 'Junior Houseman', 'Jinnah Hospital Lahore'),

(114, '2015-01-01', '2019-12-31', 'Junior Houseman', 'Jinnah Hospital Karachi'),
(114, '2020-01-01', '2024-05-31', 'Senior Houseman', 'Dow Hospital Karachi'),
(114, '2024-06-15', NULL, 'Senior Houseman', 'Shifa International Hospital'),

(112, '2008-03-01', '2013-02-28', 'Junior Houseman', 'Khyber Teaching Hospital'),
(112, '2013-03-01', '2018-06-30', 'Senior Houseman', 'Lady Reading Hospital'),
(112, '2018-07-01', '2022-12-31', 'Assistant Registrar', 'Shifa International Hospital'),
(112, '2023-01-01', NULL, 'Registrar', 'PIMS Islamabad'),

(115, '2017-08-01', '2021-07-31', 'Junior Houseman', 'Holy Family Hospital Rawalpindi'),
(115, '2021-08-01', '2024-12-31', 'Senior Houseman', 'PIMS Islamabad'),
(115, '2025-01-10', NULL, 'Assistant Registrar', 'Aga Khan Hospital Karachi'),

(116, '2018-01-01', '2024-06-30', 'Student', 'Fatima Jinnah Medical University'),
(116, '2025-02-20', NULL, 'Junior Houseman', 'Jinnah Hospital Lahore');
GO


-- This is only for checking
SELECT 'STAFF'              AS [Table], COUNT(*) AS [Rows] FROM STAFF
UNION ALL SELECT 'WARD',              COUNT(*) FROM WARD
UNION ALL SELECT 'CARE_UNIT',         COUNT(*) FROM CARE_UNIT
UNION ALL SELECT 'DOCTOR',            COUNT(*) FROM DOCTOR
UNION ALL SELECT 'CONSULTANT',        COUNT(*) FROM CONSULTANT
UNION ALL SELECT 'NURSE',             COUNT(*) FROM NURSE
UNION ALL SELECT 'BED',               COUNT(*) FROM BED
UNION ALL SELECT 'PATIENT',           COUNT(*) FROM PATIENT
UNION ALL SELECT 'COMPLAINT',         COUNT(*) FROM COMPLAINT
UNION ALL SELECT 'TREATMENT',         COUNT(*) FROM TREATMENT
UNION ALL SELECT 'MEDICAL_HISTORY',   COUNT(*) FROM MEDICAL_HISTORY
UNION ALL SELECT 'PERFORMANCE_RECORD',COUNT(*) FROM PERFORMANCE_RECORD
UNION ALL SELECT 'EXPERIENCE_RECORD', COUNT(*) FROM EXPERIENCE_RECORD;
GO
