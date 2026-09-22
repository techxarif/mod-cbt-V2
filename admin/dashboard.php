<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireTeacher();

$teacher_name = $_SESSION['teacher_name'] ?? $_SESSION['username'] ?? 'Administrator';
$total_students = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetchColumn();
$total_tests = (int)$pdo->query("SELECT COUNT(*) FROM tests")->fetchColumn();
$completed_tests = (int)$pdo->query("SELECT COUNT(*) FROM tests WHERE status='completed'")->fetchColumn();
$total_questions = (int)$pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();
$total_submissions = (int)$pdo->query("SELECT COUNT(*) FROM student_tests WHERE status='submitted'")->fetchColumn();

$stmt=$pdo->query("SELECT id,title,duration_minutes,total_marks,status FROM tests WHERE status IN ('waiting','active') ORDER BY CASE WHEN status='active' THEN 0 ELSE 1 END,id DESC LIMIT 1");
$current_exam=$stmt->fetch(PDO::FETCH_ASSOC);
$current_stats=['assigned'=>0,'submitted'=>0,'started'=>0];
if($current_exam){
  $stmt=$pdo->prepare("SELECT COUNT(*) assigned,SUM(status='submitted') submitted,SUM(status='started') started FROM student_tests WHERE test_id=?");
  $stmt->execute([(int)$current_exam['id']]);
  $r=$stmt->fetch(PDO::FETCH_ASSOC);
  $current_stats=['assigned'=>(int)($r['assigned']??0),'submitted'=>(int)($r['submitted']??0),'started'=>(int)($r['started']??0)];
}
$stmt=$pdo->query("SELECT t.id,t.title,t.duration_minutes,t.status,t.created_at,COUNT(st.id) assigned_students,SUM(st.status='submitted') submitted_students FROM tests t LEFT JOIN student_tests st ON st.test_id=t.id GROUP BY t.id,t.title,t.duration_minutes,t.status,t.created_at ORDER BY t.id DESC LIMIT 6");
$recent_tests=$stmt->fetchAll(PDO::FETCH_ASSOC);
function sc($s){return match($s){'active'=>'active','waiting'=>'waiting','completed'=>'completed',default=>'draft'};}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>MODUS CBT — Dashboard</title>
<style>
:root{--bg:#060a0f;--panel:#0d141d;--panel2:#111a25;--line:rgba(255,255,255,.075);--line2:rgba(255,255,255,.11);--text:#f4f7fb;--muted:#8995a6;--green:#20e596;--green2:#0dbb76;--blue:#55a8ff;--orange:#ffb454;--red:#ff6b6b;--ease:cubic-bezier(.22,1,.36,1)}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:#060a0f;color:var(--text);font-family:Inter,Segoe UI,Arial,sans-serif;overflow-x:hidden}.app{min-height:100vh;position:relative}
body:before{content:"";position:fixed;inset:-20%;z-index:-4;background:radial-gradient(circle at 78% 4%,rgba(30,104,150,.22),transparent 28%),radial-gradient(circle at 20% 0%,rgba(20,124,85,.17),transparent 25%),radial-gradient(circle at 55% 95%,rgba(43,55,120,.10),transparent 28%);animation:ambient 18s ease-in-out infinite alternate;pointer-events:none}
body:after{content:"";position:fixed;inset:0;z-index:-3;opacity:.24;background-image:linear-gradient(rgba(255,255,255,.022) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.022) 1px,transparent 1px);background-size:54px 54px;mask-image:linear-gradient(to bottom,#000,transparent 88%);pointer-events:none;animation:gridDrift 26s linear infinite}
@keyframes ambient{0%{transform:translate3d(-1%,0,0) scale(1)}100%{transform:translate3d(1.5%,2%,0) scale(1.04)}}@keyframes gridDrift{to{background-position:54px 54px}}
.sidebar{position:fixed;left:0;top:0;bottom:0;width:248px;padding:20px 13px;background:rgba(7,12,18,.86);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border-right:1px solid var(--line);display:flex;flex-direction:column;z-index:20;animation:sidebarIn .8s var(--ease) both;box-shadow:18px 0 60px rgba(0,0,0,.14)}
@keyframes sidebarIn{from{opacity:0;transform:translateX(-24px)}to{opacity:1;transform:none}}
.brand{display:flex;gap:11px;align-items:center;padding:5px 10px 27px}.logo{width:38px;height:38px;border-radius:11px;background:linear-gradient(135deg,#39f2a8,#0bb876);color:#04120b;display:grid;place-items:center;font-weight:950;box-shadow:0 0 35px rgba(32,229,150,.14);position:relative;overflow:hidden}.logo:after{content:"";position:absolute;inset:-50%;background:linear-gradient(110deg,transparent 42%,rgba(255,255,255,.45) 50%,transparent 58%);animation:logoSweep 5s ease-in-out infinite}@keyframes logoSweep{0%,55%{transform:translateX(-70%) rotate(10deg)}80%,100%{transform:translateX(70%) rotate(10deg)}}.brand b{font-size:17px}.brand small{display:block;color:#657286;font-size:9px;letter-spacing:1.4px;text-transform:uppercase;margin-top:3px}.label{color:#596678;font-size:9px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;padding:13px 12px 8px}.nav{display:grid;gap:3px}.nav a{height:43px;display:flex;align-items:center;gap:12px;padding:0 12px;border-radius:10px;color:#9ba7b8;font-size:12px;font-weight:650;text-decoration:none;transition:transform .35s var(--ease),background .35s ease,color .35s ease}.nav a:hover{background:#ffffff09;color:#fff;transform:translateX(3px)}.nav a.active{color:#fff;background:linear-gradient(90deg,#19d38a29,#19d38a08);box-shadow:inset 3px 0 var(--green)}.ico{width:18px;text-align:center;color:#8290a3}.active .ico{color:var(--green)}
.user{margin-top:auto;border-top:1px solid var(--line);padding:15px 9px 8px;display:flex;gap:10px;align-items:center}.avatar{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#172231;color:var(--green);font-weight:850;font-size:12px;box-shadow:inset 0 0 0 1px rgba(255,255,255,.05)}.user b{font-size:11px}.user small{display:block;color:#657286;font-size:9px;margin-top:3px}
.main{margin-left:248px;padding:28px 30px 55px;position:relative}.top{display:flex;justify-content:space-between;gap:20px;margin-bottom:28px;animation:rise .8s .08s var(--ease) both}.eyebrow{color:var(--green);font-size:10px;font-weight:850;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:7px}.eyebrow:before{content:"";display:inline-block;width:5px;height:5px;border-radius:50%;background:var(--green);box-shadow:0 0 14px var(--green);margin:0 7px 1px 0;animation:softPulse 2.4s ease-in-out infinite}h1{margin:0;font-size:30px;letter-spacing:-.8px}.sub{color:var(--muted);font-size:12px;margin-top:7px}.actions{display:flex;gap:9px}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:39px;padding:0 14px;border:1px solid var(--line);border-radius:9px;background:rgba(17,26,37,.8);color:#fff;font-size:11px;font-weight:750;text-decoration:none;transition:transform .35s var(--ease),border-color .35s ease,background .35s ease,box-shadow .35s ease;position:relative;overflow:hidden}.btn:before{content:"";position:absolute;inset:0;background:linear-gradient(110deg,transparent 25%,rgba(255,255,255,.10) 50%,transparent 75%);transform:translateX(-130%);transition:transform .7s var(--ease)}.btn:hover{transform:translateY(-2px);border-color:rgba(255,255,255,.14);box-shadow:0 10px 28px rgba(0,0,0,.22)}.btn:hover:before{transform:translateX(130%)}.btn.primary{background:var(--green);border-color:var(--green);color:#04120b;box-shadow:0 8px 28px rgba(32,229,150,.10)}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:13px;margin-bottom:17px}.stat,.panel{background:linear-gradient(145deg,rgba(14,21,31,.92),rgba(9,14,21,.90));border:1px solid var(--line);border-radius:16px}.stat{padding:18px;position:relative;overflow:hidden;animation:cardIn .75s var(--ease) both;transition:transform .45s var(--ease),border-color .45s ease,box-shadow .45s ease}.stat:nth-child(1){animation-delay:.18s}.stat:nth-child(2){animation-delay:.25s}.stat:nth-child(3){animation-delay:.32s}.stat:nth-child(4){animation-delay:.39s}.stat:before{content:"";position:absolute;width:120px;height:120px;right:-70px;top:-75px;background:var(--green);filter:blur(55px);opacity:.055;transition:opacity .5s ease}.stat:hover{transform:translateY(-5px);border-color:var(--line2);box-shadow:0 22px 50px rgba(0,0,0,.24)}.stat:hover:before{opacity:.12}.stathead,.panelhead,.examtop{display:flex;align-items:center;justify-content:space-between;gap:12px}.statlabel{font-size:10px;color:var(--muted);font-weight:750;text-transform:uppercase;letter-spacing:.7px}.staticon,.quickicon{display:grid;place-items:center;background:#19d38a1a;color:var(--green);border-radius:9px}.staticon{width:31px;height:31px}.value{font-size:27px;font-weight:850;margin-top:12px;letter-spacing:-1px}.foot{font-size:10px;color:#637084;margin-top:4px}.grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(285px,.72fr);gap:17px}.panel{overflow:hidden;box-shadow:0 18px 45px #0000002e;animation:rise .85s .44s var(--ease) both}.panel:nth-child(2){animation-delay:.52s}.panelhead{padding:18px 20px;border-bottom:1px solid var(--line)}.ptitle{font-size:13px;font-weight:800}.psub{font-size:10px;color:#697587;margin-top:4px}.link{font-size:10px;color:var(--green);font-weight:750;text-decoration:none;transition:color .25s ease,transform .25s ease}.link:hover{color:#7bffc5;transform:translateX(2px)}.body{padding:20px}.exam{border:1px solid #19d38a29;border-radius:14px;padding:21px;background:radial-gradient(circle at 100% 0,#19d38a1f,transparent 43%),linear-gradient(135deg,#19d38a0e,#ffffff03);position:relative;overflow:hidden;transition:border-color .4s ease,transform .4s var(--ease)}.exam:after{content:"";position:absolute;top:-100%;left:-35%;width:45%;height:300%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.025),transparent);transform:rotate(18deg);animation:panelSweep 8s ease-in-out infinite;pointer-events:none}@keyframes panelSweep{0%,65%{left:-45%}100%{left:130%}}.exam:hover{transform:translateY(-2px);border-color:#19d38a45}.etitle{font-size:20px;font-weight:850}.emeta{font-size:11px;color:var(--muted);margin-top:7px}.badge{display:inline-flex;align-items:center;gap:6px;padding:6px 9px;border-radius:999px;font-size:9px;font-weight:850;text-transform:uppercase;white-space:nowrap}.badge:before{content:"";width:5px;height:5px;border-radius:50%;background:currentColor;box-shadow:0 0 9px currentColor}.active{color:var(--green);background:#19d38a1a}.waiting{color:var(--orange);background:#ffb4541a}.completed{color:var(--blue);background:#55a8ff1a}.draft{color:#8995a6;background:#8995a61a}.progress{margin-top:24px}.prow{display:flex;justify-content:space-between;color:#8490a1;font-size:10px;margin-bottom:7px}.prow strong{color:#dfe7ef}.track{height:7px;background:#192330;border-radius:99px;overflow:hidden}.bar{height:100%;background:linear-gradient(90deg,#0dbb76,#38eda8);border-radius:99px;transform-origin:left;animation:progressIn 1.2s .8s var(--ease) both}@keyframes progressIn{from{transform:scaleX(0)}to{transform:scaleX(1)}}.examactions{display:flex;gap:8px;margin-top:18px;flex-wrap:wrap}.quick{padding:8px}.quickitem{display:flex;gap:12px;align-items:center;padding:11px 10px;border-radius:10px;text-decoration:none;transition:background .3s ease,transform .35s var(--ease)}.quickitem:hover{background:#ffffff0a;transform:translateX(4px)}.quickicon{width:35px;height:35px;background:#151f2c;flex:0 0 auto}.qname{font-size:11px;font-weight:750;color:#f2f5f8}.qdesc{font-size:9px;color:#657286;margin-top:3px}.recent{margin-top:17px;animation-delay:.62s}.tablewrap{overflow:auto}table{width:100%;min-width:650px;border-collapse:collapse}th,td{text-align:left;padding:13px 20px;border-bottom:1px solid var(--line)}th{font-size:8px;color:#637084;text-transform:uppercase;letter-spacing:1px}td{font-size:11px;color:#c9d1dd}tbody tr{transition:background .25s ease,transform .3s var(--ease)}tbody tr:hover{background:rgba(255,255,255,.025)}tr:last-child td{border:0}.testname{font-weight:750;color:#f3f6fa;text-decoration:none}.testname:hover{color:#7bffc5}.testid{font-size:9px;color:#657286;margin-top:3px}.empty{text-align:center;padding:48px;color:var(--muted);font-size:11px}.mobile{display:none}
@keyframes rise{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}@keyframes cardIn{from{opacity:0;transform:translateY(24px) scale(.985)}to{opacity:1;transform:none}}@keyframes softPulse{0%,100%{opacity:.7;transform:scale(1)}50%{opacity:1;transform:scale(1.35)}}
@media(max-width:1080px){.stats{grid-template-columns:repeat(2,1fr)}.grid{grid-template-columns:1fr}}@media(max-width:800px){.sidebar{transform:translateX(-100%);transition:transform .45s var(--ease)}.sidebar.open{transform:none}.main{margin-left:0;padding:20px 15px}.mobile{display:inline-flex;width:38px;padding:0}.actions .btn:not(.primary){display:none}}@media(max-width:520px){.stats{grid-template-columns:1fr}.top{align-items:flex-start}h1{font-size:25px}.examtop{flex-direction:column;align-items:flex-start}.actions{gap:6px}.main{padding-top:18px}.panelhead{padding:16px}.body{padding:15px}}
@media(prefers-reduced-motion:reduce){*,*:before,*:after{animation-duration:.01ms!important;animation-iteration-count:1!important;scroll-behavior:auto!important;transition-duration:.01ms!important}}
</style>
</head>
<body>
<div class="app">
<aside class="sidebar" id="sidebar">
<div class="brand"><div class="logo">M</div><div><b>MODUS CBT</b><small>Examination System</small></div></div>
<div class="label">Workspace</div>
<nav class="nav">
<a class="active" href="dashboard.php"><span class="ico">⌂</span>Dashboard</a>
<a href="students.php"><span class="ico">♙</span>Students</a>
<a href="tests.php"><span class="ico">▣</span>Tests</a>
<a href="questions.php"><span class="ico">☷</span>Questions</a>
<a href="assign_students.php"><span class="ico">↔</span>Assignments</a>
<a href="exam_control.php"><span class="ico">▶</span>Exam Control</a>
<a href="results.php"><span class="ico">▤</span>Results</a>
<a href="results.php"><span class="ico">⌁</span>Faculty QR</a>
</nav>
<div class="label">System</div>
<nav class="nav"><a href="../logout.php"><span class="ico">↪</span>Logout</a></nav>
<div class="user"><div class="avatar"><?=htmlspecialchars(strtoupper(substr($teacher_name,0,1)))?></div><div><b><?=htmlspecialchars($teacher_name)?></b><small>Teacher / Administrator</small></div></div>
</aside>
<main class="main">
<div class="top"><div><div class="eyebrow">Control Center</div><h1>Dashboard</h1><div class="sub">Monitor examinations, students and results from one place.</div></div><div class="actions"><button class="btn mobile" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button><a class="btn" href="tests.php">View Tests</a><a class="btn primary" href="create_test.php">+ Create Test</a></div></div>
<section class="stats">
<div class="stat"><div class="stathead"><span class="statlabel">Active Students</span><span class="staticon">♙</span></div><div class="value"><?=number_format($total_students)?></div><div class="foot">Available for assignment</div></div>
<div class="stat"><div class="stathead"><span class="statlabel">Total Tests</span><span class="staticon">▣</span></div><div class="value"><?=number_format($total_tests)?></div><div class="foot"><?=number_format($completed_tests)?> completed</div></div>
<div class="stat"><div class="stathead"><span class="statlabel">Question Bank</span><span class="staticon">☷</span></div><div class="value"><?=number_format($total_questions)?></div><div class="foot">Available questions</div></div>
<div class="stat"><div class="stathead"><span class="statlabel">Submissions</span><span class="staticon">✓</span></div><div class="value"><?=number_format($total_submissions)?></div><div class="foot">Submitted attempts</div></div>
</section>
<section class="grid">
<div class="panel"><div class="panelhead"><div><div class="ptitle">Current Examination</div><div class="psub">Live examination status</div></div><a class="link" href="exam_control.php">Open Control →</a></div>
<?php if($current_exam): $assigned=$current_stats['assigned'];$submitted=$current_stats['submitted'];$progress=$assigned?min(100,($submitted/$assigned)*100):0; ?>
<div class="body"><div class="exam"><div class="examtop"><div><div class="etitle"><?=htmlspecialchars($current_exam['title'])?></div><div class="emeta">Test #<?=intval($current_exam['id'])?> · <?=intval($current_exam['duration_minutes'])?> minutes · <?=htmlspecialchars((string)$current_exam['total_marks'])?> marks</div></div><span class="badge <?=sc($current_exam['status'])?>"><?=ucfirst($current_exam['status'])?></span></div><div class="progress"><div class="prow"><span>Submission progress</span><strong><?=$submitted?> / <?=$assigned?></strong></div><div class="track"><div class="bar" style="width:<?=$progress?>%"></div></div></div><div class="examactions"><a class="btn primary" href="exam_control.php?test_id=<?=intval($current_exam['id'])?>">Manage Exam</a><a class="btn" href="results.php?test_id=<?=intval($current_exam['id'])?>">View Results</a></div></div></div>
<?php else: ?>
<div class="empty"><strong style="color:#dce2ea">No active examination</strong><div style="margin-top:7px">Prepare a test and start it from Exam Control.</div><div style="margin-top:15px"><a class="btn primary" href="create_test.php">Create Test</a></div></div>
<?php endif; ?></div>
<div class="panel"><div class="panelhead"><div><div class="ptitle">Quick Actions</div><div class="psub">Common administration tasks</div></div></div><div class="quick"><a class="quickitem" href="import_students.php"><span class="quickicon">↑</span><span><div class="qname">Import Students</div><div class="qdesc">Upload student CSV data</div></span></a><a class="quickitem" href="create_test.php"><span class="quickicon">+</span><span><div class="qname">Create Test</div><div class="qdesc">Set up a new examination</div></span></a><a class="quickitem" href="add_question.php"><span class="quickicon">?</span><span><div class="qname">Add Questions</div><div class="qdesc">Build your question paper</div></span></a><a class="quickitem" href="assign_students.php"><span class="quickicon">↔</span><span><div class="qname">Assign Students</div><div class="qdesc">Assign students to a test</div></span></a></div></div>
</section>
<section class="panel recent"><div class="panelhead"><div><div class="ptitle">Recent Tests</div><div class="psub">Latest examinations created in MODUS CBT</div></div><a class="link" href="tests.php">View all →</a></div><div class="tablewrap">
<?php if($recent_tests): ?><table><thead><tr><th>Test</th><th>Status</th><th>Duration</th><th>Students</th><th>Submitted</th><th>Created</th></tr></thead><tbody><?php foreach($recent_tests as $test): ?><tr><td><a class="testname" href="tests.php"><?=htmlspecialchars($test['title'])?></a><div class="testid">TEST #<?=intval($test['id'])?></div></td><td><span class="badge <?=sc($test['status'])?>"><?=ucfirst($test['status'])?></span></td><td><?=intval($test['duration_minutes'])?> min</td><td><?=intval($test['assigned_students'])?></td><td><?=intval($test['submitted_students']??0)?></td><td><?=htmlspecialchars(date('d M Y',strtotime($test['created_at'])))?></td></tr><?php endforeach;?></tbody></table><?php else: ?><div class="empty">No tests have been created yet.</div><?php endif; ?>
</div></section>
</main>
</div>
<script>
// Cinematic entry timing for table rows and quick actions.
document.addEventListener('DOMContentLoaded',()=>{
 document.querySelectorAll('.quickitem').forEach((el,i)=>{el.style.opacity='0';el.style.transform='translateX(-8px)';setTimeout(()=>{el.style.transition='opacity .55s var(--ease),transform .55s var(--ease)';el.style.opacity='1';el.style.transform='none'},620+i*70)});
 document.querySelectorAll('tbody tr').forEach((el,i)=>{el.style.opacity='0';el.style.transform='translateY(7px)';setTimeout(()=>{el.style.transition='opacity .5s var(--ease),transform .5s var(--ease)';el.style.opacity='1';el.style.transform='none'},760+i*55)});
});
</script>
</body>
</html>
