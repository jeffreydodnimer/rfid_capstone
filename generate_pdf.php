<?php
// generate_sf2_exact.php — A4 landscape SF2 matching the provided layout
ob_start();
require_once('fpdf.php');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

// --- DB config ---
$host='localhost'; $db='rfid_capstone'; $user='root'; $pass='';

// --- Inputs ---
$section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 1;
$month_ym   = isset($_GET['month']) ? preg_replace('/[^0-9\-]/','',$_GET['month']) : date('Y-m');
$school_year = $_GET['school_year'] ?? '';
$school_id   = $_GET['school_id']   ?? '301394';
$school_name = $_GET['school_name'] ?? 'SAN ISIDRO NATIONAL HIGH SCHOOL';
$adviser     = $_GET['adviser_name']?? 'Adviser Name';
$head        = $_GET['school_head_name'] ?? 'School Head Name';
$logo_path   = 'deped_logo.png'; // optional

if(!preg_match('/^\d{4}-\d{2}$/',$month_ym)) $month_ym = date('Y-m');
$Y = (int)substr($month_ym,0,4);
$M = (int)substr($month_ym,5,2);
$month_name = date('F', mktime(0,0,0,$M,1,$Y));
$days_in_month = cal_days_in_month(CAL_GREGORIAN,$M,$Y);

// --- DB connect ---
try {
  $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4",$user,$pass,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
  ]);
} catch(PDOException $e){ ob_end_clean(); die("DB error: ".$e->getMessage()); }

// Auto school year if empty (PH calendar)
if(empty($school_year)){
  $start = ($M>=6)?$Y:$Y-1; $school_year = $start.'-'.($start+1);
}

// Section info
$sec = $pdo->prepare("SELECT section_name, grade_level FROM sections WHERE section_id=:id");
$sec->execute(['id'=>$section_id]);
$secrow = $sec->fetch();
if(!$secrow){ ob_end_clean(); die("Section not found."); }
$section_name = $secrow['section_name'];
$grade_level  = (stripos((string)$secrow['grade_level'],'grade')===false? 'Grade ':'').$secrow['grade_level'];

// Students
$st = $pdo->prepare("
  SELECT s.lrn, s.gender,
         CONCAT(s.lastname, ', ', s.firstname, COALESCE(CONCAT(' ', s.middlename), '')) AS name
  FROM students s
  JOIN enrollments e ON s.lrn=e.lrn
  WHERE e.section_id=:sid AND e.school_year=:sy
  ORDER BY CASE LOWER(s.gender) WHEN 'male' THEN 0 ELSE 1 END, s.lastname, s.firstname
");
$st->execute(['sid'=>$section_id,'sy'=>$school_year]);
$students = $st->fetchAll();
if(!$students){ ob_end_clean(); die("No enrolled students for this section/year."); }

$male = array_values(array_filter($students, fn($x)=>strtolower($x['gender'])==='male'));
$fem  = array_values(array_filter($students, fn($x)=>strtolower($x['gender'])!=='male'));

// Attendance (present only; absent inferred)
$att = $pdo->prepare("
  SELECT s.lrn, a.date
  FROM attendance a
  JOIN enrollments e ON a.enrollment_id=e.enrollment_id
  JOIN students s ON e.lrn=s.lrn
  WHERE e.section_id=:sid AND e.school_year=:sy
    AND DATE_FORMAT(a.date,'%Y-%m')=:m AND a.status='present'
");
$att->execute(['sid'=>$section_id,'sy'=>$school_year,'m'=>$month_ym]);
$present_rows = $att->fetchAll();
$present = [];
foreach($present_rows as $r){ $d=(int)date('d',strtotime($r['date'])); $present[$r['lrn']][$d]=true; }

// Determine school days (Mon–Fri)
$school_days = [];
for($d=1;$d<=$days_in_month;$d++){ if((int)date('N',strtotime("$Y-$M-$d"))<=5){ $school_days[]=$d; } }

// --- PDF class to mirror layout ---
class SF2PDF extends FPDF {
  public $school_id,$school_name,$school_year,$month_name,$grade_level,$section_name,$logo_path;
  function __construct(){ parent::__construct('L','mm','A4'); }
  function setMeta($sid,$sname,$sy,$mname,$grade,$section,$logo){
    $this->school_id=$sid; $this->school_name=$sname; $this->school_year=$sy;
    $this->month_name=$mname; $this->grade_level=$grade; $this->section_name=$section;
    $this->logo_path=$logo;
  }
  function Header(){
    // Logo placeholder like screenshot (top-left)
    if($this->logo_path && file_exists($this->logo_path)) $this->Image($this->logo_path,6,5,12);

    // Title
    $this->SetFont('Arial','B',10);
    $this->Cell(0,5,'School Form 2 (SF2) Daily Attendance Report of Learners',0,1,'C');
    $this->SetFont('Arial','',6.2);
    $this->Cell(0,3.5,'(This replaces Form 1, Form 2 & STS Form 4 - Absenteeism and Dropout Profile)',0,1,'C');
    $this->Ln(1);

    // Header boxes row 1
    $this->SetFont('Arial','',6);
    $this->Cell(14,4,'School ID',1,0,'L');
    $this->SetFont('Arial','B',7); $this->Cell(20,4,$this->school_id,1,0,'C');
    $this->SetFont('Arial','',6);  $this->Cell(14,4,'School Year',1,0,'L');
    $this->SetFont('Arial','B',7); $this->Cell(22,4,$this->school_year,1,0,'C');
    $this->SetFont('Arial','',6);  $this->Cell(34,4,'Report for the Month of',1,0,'L');
    $this->SetFont('Arial','B',7); $this->Cell(22,4,$this->month_name,1,0,'C');
    $this->SetFont('Arial','',6);  $this->Cell(18,4,'Grade Level',1,0,'L');
    $this->SetFont('Arial','B',7); $this->Cell(18,4,$this->grade_level,1,0,'C');
    $this->SetFont('Arial','',6);  $this->Cell(14,4,'Section',1,0,'L');
    $this->SetFont('Arial','B',7); $this->Cell(24,4,$this->section_name,1,1,'C');

    // Header boxes row 2
    $this->SetFont('Arial','',6);
    $this->Cell(22,4,'Name of School',1,0,'L');
    $this->SetFont('Arial','B',7);
    $this->Cell(0,4,$this->school_name,1,1,'L');
    $this->Ln(1);
  }
}

// --- Draw attendance table like the image ---
function drawGrid($pdf,$label,$list,$present,$school_days,$Y,$M,$is_first=false){
  // Sizes aligned to screenshot
  $name_w = 70;
  $day_w  = 4.4;
  $h_hdr  = 3.2;
  $h_row  = 3.2;
  $w_abs  = 12;
  $w_tar  = 12;
  $w_rem  = 40;

  if($is_first){
    // Header: Learner's Name + days block + totals + remarks
    $pdf->SetFont('Arial','B',6.2);
    $pdf->Cell($name_w, $h_hdr*2, "LEARNER'S NAME
(Last Name, First Name, Middle Name)", 1, 0, 'C');

    // Big days block (two sub-rows like the picture)
    $x = $pdf->GetX(); $y = $pdf->GetY();
    $pdf->Cell(count($school_days)*$day_w, $h_hdr*2, '', 1, 0, 'C');
    $pdf->Cell($w_abs, $h_hdr*2, "ABSENT
Total for the Month", 1, 0, 'C');
    $pdf->Cell($w_tar, $h_hdr*2, "TARDY
Total for the Month", 1, 0, 'C');
    $pdf->Cell($w_rem, $h_hdr*2, "REMARKS (DROPPED OUT:
state reason; TRANSFERRED IN/OUT:
write name of School.)", 1, 1, 'C');

    // Sub-rows inside days block
    $pdf->SetXY($x,$y);
    $pdf->SetFont('Arial','B',6);
    foreach($school_days as $d){ $pdf->Cell($day_w,$h_hdr,$d,1,0,'C'); }
    $pdf->SetXY($x,$y+$h_hdr);
    $pdf->SetFont('Arial','',5.5);
    foreach($school_days as $d){
      $dowN=(int)date('N',strtotime("$Y-$M-$d"));
      $txt = ['','M','T','W','Th','F','Sa','Su'][$dowN];
      $pdf->Cell($day_w,$h_hdr,$txt,1,0,'C');
    }
    $pdf->Ln($h_hdr);
  }

  // Gender label row
  $pdf->SetFont('Arial','B',6.5);
  $pdf->Cell($name_w,$h_row,strtoupper($label),1,0,'L');
  $pdf->Cell(count($school_days)*$day_w + $w_abs+$w_tar+$w_rem,$h_row,'',1,1);

  // Accumulators
  $daily_abs = array_fill_keys($school_days,0);
  $daily_tar = array_fill_keys($school_days,0);

  // Rows
  $pdf->SetFont('Arial','',6.2);
  foreach($list as $i=>$s){
    $nm = iconv('UTF-8','ISO-8859-1//TRANSLIT', ($i+1).'. '.$s['name']);
    $pdf->Cell($name_w,$h_row,$nm,1,0,'L');

    $absCnt=0; $tarCnt=0; // no tardy data here—kept to match columns
    foreach($school_days as $d){
      $dowN=(int)date('N',strtotime("$Y-$M-$d"));
      $isWeekend = ($dowN>=6);

      // Mon–Fri active; weekends visually hatched like picture (we'll simulate with a '/')
      if($dowN<=5){
        if(!empty($present[$s['lrn']][$d])){
          $pdf->Cell($day_w,$h_row,'',1,0,'C'); // blank = present
        } else {
          $pdf->Cell($day_w,$h_row,'X',1,0,'C'); // X = absent
          $daily_abs[$d]++; $absCnt++;
        }
      } else {
        // weekend hatched
        $pdf->SetTextColor(150,150,150);
        $pdf->Cell($day_w,$h_row,'/',1,0,'C');
        $pdf->SetTextColor(0,0,0);
      }
    }
    $pdf->Cell($w_abs,$h_row,$absCnt?:'',1,0,'C');
    $pdf->Cell($w_tar,$h_row,$tarCnt?:'',1,0,'C');
    $pdf->Cell($w_rem,$h_row,'',1,1,'L');
  }

  // Per-day totals row
  $pdf->SetFont('Arial','B',6.2);
  $pdf->Cell($name_w,$h_row,'TOTAL ABSENT (per day)',1,0,'R');
  foreach($school_days as $d){ $pdf->Cell($day_w,$h_row, $daily_abs[$d]?:'',1,0,'C'); }
  $pdf->Cell($w_abs+$w_tar+$w_rem,$h_row,'',1,1);

  return $daily_abs;
}

// --- Build the PDF page ---
$pdf = new SF2PDF();
$pdf->AliasNbPages();
$pdf->SetMargins(6,5,6);
$pdf->SetAutoPageBreak(false);
$pdf->setMeta($school_id,$school_name,$school_year,$month_name,$grade_level,$section_name,$logo_path);
$pdf->AddPage();

// Draw the two blocks (Male then Female) with identical header structure
$male_abs = drawGrid($pdf,'MALE',$male,$present,$school_days,$Y,$M,true);
$fem_abs  = drawGrid($pdf,'FEMALE',$fem,$present,$school_days,$Y,$M,false);

// Combined totals row (like the bottom strip in your image)
$name_w=70; $day_w=4.4; $h_row=3.2; $w_abs=12; $w_tar=12; $w_rem=40;
$pdf->SetFont('Arial','B',6.2);
$pdf->Cell($name_w,$h_row,'TOTAL ABSENT (Male+Female)',1,0,'R');
foreach($school_days as $d){
  $c = ($male_abs[$d]??0)+($fem_abs[$d]??0);
  $pdf->Cell($day_w,$h_row, $c?:'',1,0,'C');
}
$pdf->Cell($w_abs+$w_tar+$w_rem,$h_row,'',1,1);

// Bottom certification and signature lines (compacted)
$pdf->Ln(2);
$pdf->SetFont('Arial','',6.2);
$pdf->Cell(0,3,'I certify that this is a true and correct report.',0,1,'C');
$y = $pdf->GetY()+1;
$centerX = 148.5;
$pdf->Line($centerX-70,$y+8,$centerX+70,$y+8);
$pdf->SetY($y+8);
$pdf->SetFont('Arial','B',7);
$pdf->Cell(0,3,strtoupper($adviser),0,1,'C');
$pdf->SetFont('Arial','',5.8);
$pdf->Cell(0,3,'(Signature of Adviser over Printed Name)',0,1,'C');

$y = $pdf->GetY()+2;
$pdf->Line($centerX-70,$y+8,$centerX+70,$y+8);
$pdf->SetY($y+8);
$pdf->SetFont('Arial','B',7);
$pdf->Cell(0,3,strtoupper($head),0,1,'C');
$pdf->SetFont('Arial','',5.8);
$pdf->Cell(0,3,'(Signature of School Head over Printed Name)',0,1,'C');

// Output
ob_end_clean();
$pdf->Output('I',"SF2_{$section_name}_{$month_ym}.pdf");