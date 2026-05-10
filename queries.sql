USE IvorHospitalDB;
GO


-- Q1. A list of consultants and the doctors in their team

Select
    con.staffno as consultantno,
    sc.name as consultantname,
    con.specialty as consultantspecialty,
    d.staffno as doctorno,
    sd.name as doctorname,
    d.position as doctorposition,
    d.datejoinedteam as datejoinedteam
from consultant con
join staff sc on sc.staffno  = con.staffno
join doctor d on d.consultantno = con.staffno
join staff sd on sd.staffno  = d.staffno
order by con.staffno, d.datejoinedteam;
go


-- Q2. A list of wards with respective sisters, care units and staff nurses in charge
Select
    w.wardname,
    w.specialty,
    sday.name as daysister,
    snight.name as nightsister,
    cu.careunitno,
    sn.name as staffnurseincharge
from ward w
left join staff sday on sday.staffno = w.daysisterstaffno
left join staff snight on snight.staffno = w.nightsisterstaffno
left join care_unit cu on cu.wardname = w.wardname
left join staff sn on sn.staffno = cu.inchargestaffno
order by w.wardname, cu.careunitno;
go


-- Q3. A list of patients and their complaints, treatments and dates of treatment
Select
    p.patientno,
    p.name as patientname,
    c.description as complaint,
    t.description as treatment,
    sd.name as treatingdoctor,
    mh.datestarted,
    isnull(convert(varchar(10), mh.dateended, 120), 'ongoing') as dateended
from patient p
join medical_history mh on mh.patientno    = p.patientno
join complaint c on c.complaintcode = mh.complaintcode
join treatment t on t.treatmentcode = mh.treatmentcode
join staff sd on sd.staffno = mh.doctorno
order by p.patientno, mh.datestarted;
go


-- Q4. A list of junior housemen and their patients and the staff nurse for the care-unit
Select
    d.staffno as doctorno,
    sd.name as juniorhouseman,
    p.patientno,
    p.name as patientname,
    p.careunitno,
    sn.name as staffnurseincharge
from doctor d
join staff sd on sd.staffno = d.staffno
join patient p on p.doctorincharge = d.staffno
join care_unit cu on cu.careunitno = p.careunitno
left join staff sn on sn.staffno = cu.inchargestaffno
where d.position = 'junior houseman'
order by d.staffno, p.patientno;
go


-- Q5. A list of consultants with a unique specialty
Select
    con.staffno as consultantno,
    s.name as consultantname,
    con.specialty
from consultant con
join staff s on s.staffno = con.staffno
where con.specialty in (
    select specialty
    from consultant
    group by specialty
    having count(*) = 1
)
order by con.specialty;
go


-- Q6. A list of complaints, treatments given for that complaint and experience history of the doctor giving that treatment
Select
    c.description as complaint,
    t.description as treatment,
    sd.name as doctor,
    er.fromdate as experiencefrom,
    er.todate as experienceto,
    er.position as previousposition,
    er.establishment
from medical_history mh
join complaint c on c.complaintcode  = mh.complaintcode
join treatment t on t.treatmentcode  = mh.treatmentcode
join staff sd on sd.staffno = mh.doctorno
join experience_record er on er.doctorno = mh.doctorno
order by c.description, sd.name, er.fromdate;
go

-- Q7. A list of patients with more than one complaint and their treatments
Select
    p.patientno,
    p.name as patientname,
    c.description as complaint,
    t.description as treatment,
    mh.datestarted,
    isnull(convert(varchar(10), mh.dateended, 120), 'ongoing') as dateended
from patient p
join medical_history mh on mh.patientno = p.patientno
join complaint c on c.complaintcode = mh.complaintcode
join treatment t on t.treatmentcode = mh.treatmentcode
where p.patientno in (
    select patientno
    from medical_history
    group by patientno
    having count(distinct complaintcode) > 1
)
order by p.patientno, c.description, mh.datestarted;
go


-- Q8. A list of patients grouped by treatment within complaint
Select
    c.description as complaint,
    t.description as treatment,
    p.patientno,
    p.name as patientname,
    mh.datestarted,
    isnull(convert(varchar(10), mh.dateended, 120), 'ongoing') as dateended
from medical_history mh
join complaint c on c.complaintcode = mh.complaintcode
join treatment t on t.treatmentcode = mh.treatmentcode
join patient p on p.patientno     = mh.patientno
order by c.description, t.description, p.patientno;
go


-- Q9. A performance history for a particular doctor (replace 103 with any DoctorNo)

Declare @doctorno int = 103;

Select
    sd.name as doctorname,
    d.position as doctorposition,
    sc.name as consultantname,
    pr.gradedate,
    pr.grade as performancegrade
from performance_record pr
join doctor d on d.staffno = pr.doctorno
join staff sd on sd.staffno = d.staffno
join consultant con on con.staffno = pr.consultantno
join staff sc on sc.staffno = con.staffno
where pr.doctorno = @doctorno
order by pr.gradedate;
go


-- Q10. Full medical details for a particular patient (replace 1007 with any PatientNo)
declare @patientno int = 1007;

-- Patient header info
Select
    p.patientno,
    p.name as patientname,
    p.dateofbirth,
    p.dateadmitted,
    isnull(convert(varchar(10), p.datedischarged, 120), 'currently admitted') as status,
    w.wardname,
    w.specialty,
    cu.careunitno,
    p.bedno,
    p.doctorincharge as doctorno,
    sd.name as doctorname,
    doc.position as doctorposition,
    sc.name as consultantname,
    con.specialty as consultantspecialty
from patient p
join care_unit cu on cu.careunitno = p.careunitno
join ward w on w.wardname = cu.wardname
join doctor doc on doc.staffno = p.doctorincharge
join staff sd on sd.staffno = doc.staffno
left join consultant con on con.staffno = doc.consultantno
left join staff sc on sc.staffno  = doc.consultantno
where p.patientno = @patientno;

-- Medical history for that patient
select
    c.description  as complaint,
    t.description  as treatment,
    s.name as treatingdoctor,
    mh.datestarted,
    isnull(convert(varchar(10), mh.dateended, 120), 'ongoing') as dateended
from medical_history mh
join complaint c on c.complaintcode = mh.complaintcode
join treatment t on t.treatmentcode = mh.treatmentcode
join staff s on s.staffno = mh.doctorno
where mh.patientno = @patientno
order by mh.datestarted;
go



declare @complaintcode int  = 10;         -- e.g. 10 = fractured femur
declare @fromdate date = '2026-01-01';
declare @todate date = '2026-12-31';

Select
    c.description as complaint,
    t.description as treatment,
    p.name as patientname,
    s.name as treatingdoctor,
    mh.datestarted,
    isnull(convert(varchar(10), mh.dateended, 120), 'ongoing') as dateended
from medical_history mh
join complaint c on c.complaintcode = mh.complaintcode
join treatment t on t.treatmentcode = mh.treatmentcode
join patient p on p.patientno = mh.patientno
join staff s on s.staffno = mh.doctorno
where mh.complaintcode = @complaintcode
  and mh.datestarted between @fromdate and @todate
order by t.description, mh.datestarted;
go


Select position as staffposition,
       count(*) as numberofstaff,
       'doctor' as staffcategory
from doctor
group by position

union all

select nursetype,
       count(*),
       'nurse'
from nurse
group by nursetype

order by staffcategory, numberofstaff desc;
go