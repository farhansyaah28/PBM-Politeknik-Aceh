<?php
$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$expiresAtMs = (new DateTimeImmutable($attempt['expires_at'], new DateTimeZone('UTC')))->getTimestamp() * 1000;
$answeredInitially = count(array_filter($attempt['questions'], static fn (array $question): bool => !empty($question['selected_option'])));
$questionCount = count($attempt['questions']);
$initialQuestionIndex = 0;
foreach ($attempt['questions'] as $questionIndex => $question) {
    if (empty($question['selected_option'])) {
        $initialQuestionIndex = $questionIndex;
        break;
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $e($title) ?> · Politeknik Aceh</title><link rel="stylesheet" href="/assets/app.css"></head><body class="portal-body exam-runtime-body">
<main class="exam-page">
<header class="exam-command-bar">
  <div class="exam-brand"><img src="/assets/logo-politeknik-aceh.png" alt=""><span><strong>PMB</strong><small>Politeknik Aceh</small></span></div>
  <div class="exam-progress"><span>Progres jawaban</span><b><span id="answered-count"><?= $answeredInitially ?></span>/<?= $questionCount ?></b><small id="save-status">Jawaban tersimpan</small></div>
  <div class="timer"><span>Sisa waktu server</span><b id="timer">--:--</b><small id="fullscreen-status">Layar penuh diperiksa otomatis</small></div>
  <button class="primary exam-submit-button" type="submit" form="submit-form">Kumpulkan jawaban</button>
</header>

<div class="exam-layout">
<aside class="question-navigator" aria-label="Navigasi soal"><div class="navigator-heading"><b>Daftar soal</b><span><i class="legend-dot answered"></i> Dijawab <i class="legend-dot"></i> Belum</span></div><div class="question-number-grid"><?php foreach ($attempt['questions'] as $questionIndex => $question): ?><button type="button" data-question-nav="<?= $e($question['id']) ?>" data-question-index="<?= $e($questionIndex) ?>" class="<?= $question['selected_option'] ? 'answered ' : '' ?><?= $questionIndex === $initialQuestionIndex ? 'is-current' : '' ?>" aria-label="Tampilkan soal <?= $e($question['position']) ?>" <?= $questionIndex === $initialQuestionIndex ? 'aria-current="true"' : '' ?>><?= $e($question['position']) ?></button><?php endforeach; ?></div><div class="navigator-summary"><span>Soal aktif</span><strong><span id="question-current"><?= $initialQuestionIndex + 1 ?></span>/<?= $questionCount ?></strong><small id="unanswered-summary"><?= $questionCount - $answeredInitially ?> soal belum dijawab</small></div></aside>

<form class="exam-question-form" id="submit-form" method="post" action="/ujian/kumpulkan"><input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>"><input type="hidden" name="attempt_id" value="<?= $e($attempt['id']) ?>">
<div class="question-stack" aria-live="polite"><?php foreach ($attempt['questions'] as $questionIndex => $question): ?><article class="question-card <?= $questionIndex === $initialQuestionIndex ? 'is-active' : '' ?>" id="question-<?= $e($question['position']) ?>" data-question-card="<?= $e($question['id']) ?>" data-question-index="<?= $e($questionIndex) ?>" tabindex="-1" <?= $questionIndex === $initialQuestionIndex ? '' : 'hidden' ?>><p class="question-position">Soal <?= $e($question['position']) ?> dari <?= $questionCount ?></p><h1><?= nl2br($e($question['question_text'])) ?></h1><div class="answer-options"><?php foreach ($question['order'] as $option): ?><label class="answer-option"><input type="radio" name="answer-<?= $e($question['id']) ?>" value="<?= $e($option) ?>" data-question-id="<?= $e($question['id']) ?>" <?= $question['selected_option'] === $option ? 'checked' : '' ?>><span><b><?= $e($option) ?>.</b> <?= $e($question['options'][$option]) ?></span></label><?php endforeach; ?></div></article><?php endforeach; ?></div>
<footer class="question-actions"><button class="quiet-button" id="previous-question" type="button">Soal sebelumnya</button><span>Pilih nomor soal atau gunakan tombol navigasi.</span><button class="primary" id="next-question" type="button">Soal berikutnya</button></footer>
</form></div>
<div class="fullscreen-gate" id="fullscreen-gate" role="dialog" aria-modal="true" aria-labelledby="fullscreen-gate-title" hidden><div><h2 id="fullscreen-gate-title">Lanjutkan dalam layar penuh</h2><p>Browser membutuhkan satu konfirmasi untuk mengaktifkan layar penuh setelah halaman ujian dibuka.</p><button class="primary" id="fullscreen-retry" type="button">Lanjutkan ujian</button></div></div>
<dialog class="portal-modal" id="submit-confirm-modal">
  <div class="portal-modal-content">
    <div class="portal-modal-header">
      <div class="modal-icon-badge warning-badge">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <div>
        <h3>Konfirmasi Kumpulkan Ujian</h3>
        <p>Periksa kembali sebelum mengumpulkan jawaban Anda</p>
      </div>
      <button type="button" class="modal-close-btn" id="modal-close-x" aria-label="Tutup modal">&times;</button>
    </div>
    <div class="portal-modal-body">
      <p>Apakah Anda yakin ingin mengumpulkan jawaban ujian sekarang?</p>
      <ul class="modal-info-list" style="margin-top:10px;">
        <li>Soal dijawab: <strong id="modal-answered-info"><?= $answeredInitially ?></strong> dari <strong><?= $questionCount ?></strong> soal</li>
        <li>Sisa waktu: <strong id="modal-timer-info">--:--</strong></li>
        <li style="color:var(--red,#b42318);"><strong>Peringatan:</strong> Jawaban tidak dapat diubah setelah dikumpulkan.</li>
      </ul>
    </div>
    <div class="portal-modal-footer">
      <button type="button" class="quiet-button" id="modal-cancel-btn">Batal</button>
      <button type="button" class="primary" id="modal-confirm-submit-btn">Ya, Kumpulkan Jawaban</button>
    </div>
  </div>
</dialog>
<script>
const answerEndpoint='/ujian/jawaban',securityEndpoint='/ujian/security/event',csrf=<?= json_encode($_SESSION['csrf']) ?>,attemptId=<?= json_encode($attempt['id']) ?>,expiry=<?= $expiresAtMs ?>;
const examPage=document.querySelector('.exam-page'),timer=document.getElementById('timer'),saveStatus=document.getElementById('save-status'),submitForm=document.getElementById('submit-form'),answeredCount=document.getElementById('answered-count'),unansweredSummary=document.getElementById('unanswered-summary'),questionCurrent=document.getElementById('question-current'),previousQuestion=document.getElementById('previous-question'),nextQuestion=document.getElementById('next-question'),fullscreenGate=document.getElementById('fullscreen-gate'),fullscreenRetry=document.getElementById('fullscreen-retry'),fullscreenStatus=document.getElementById('fullscreen-status');
const submitConfirmModal=document.getElementById('submit-confirm-modal'),modalAnsweredInfo=document.getElementById('modal-answered-info'),modalTimerInfo=document.getElementById('modal-timer-info'),modalCancelBtn=document.getElementById('modal-cancel-btn'),modalCloseX=document.getElementById('modal-close-x'),modalConfirmSubmitBtn=document.getElementById('modal-confirm-submit-btn');
const questionCards=[...document.querySelectorAll('[data-question-card]')],questionNavigation=[...document.querySelectorAll('[data-question-nav]')];
let submitted=false,securityPaused=false;
function updateProgress(){const answered=new Set([...document.querySelectorAll('input[type=radio]:checked')].map(input=>input.dataset.questionId));document.querySelectorAll('[data-question-nav]').forEach(link=>link.classList.toggle('answered',answered.has(link.dataset.questionNav)));answeredCount.textContent=answered.size;const unanswered=document.querySelectorAll('[data-question-nav]').length-answered.size;unansweredSummary.textContent=unanswered+' soal belum dijawab';}
let activeQuestion=<?= $initialQuestionIndex ?>;
function showQuestion(index,focusQuestion=false){activeQuestion=Math.max(0,Math.min(questionCards.length-1,index));questionCards.forEach((card,cardIndex)=>{const active=cardIndex===activeQuestion;card.hidden=!active;card.classList.toggle('is-active',active);});questionNavigation.forEach((button,buttonIndex)=>{const active=buttonIndex===activeQuestion;button.classList.toggle('is-current',active);if(active)button.setAttribute('aria-current','true');else button.removeAttribute('aria-current');});questionCurrent.textContent=activeQuestion+1;previousQuestion.disabled=activeQuestion===0;nextQuestion.disabled=activeQuestion===questionCards.length-1;if(focusQuestion)questionCards[activeQuestion].focus();}
function tick(){const remaining=Math.max(0,expiry-Date.now()),seconds=Math.floor(remaining/1000);timer.textContent=String(Math.floor(seconds/60)).padStart(2,'0')+':'+String(seconds%60).padStart(2,'0');timer.classList.toggle('urgent',seconds<=300);if(remaining===0&&!submitted){submitted=true;if(submitConfirmModal.open)submitConfirmModal.close();saveStatus.textContent='Waktu habis. Mengumpulkan jawaban…';submitForm.submit();}}
async function securityEvent(type){if(securityPaused||submitted)return;try{const body=new URLSearchParams({csrf,attempt_id:attemptId,event_type:type});const response=await fetch(securityEndpoint,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});const data=await response.json();if(data.paused){securityPaused=true;document.querySelectorAll('input[type=radio],button').forEach(control=>control.disabled=true);saveStatus.textContent='Attempt dijeda untuk review Admin.';alert('Ujian dijeda karena batas insiden keamanan tercapai. Admin perlu meninjau attempt Anda.');location.href='/ujian';}}catch{}}
function setFullscreenGate(open){fullscreenGate.hidden=!open;if(open){examPage.setAttribute('inert','');requestAnimationFrame(()=>fullscreenRetry.focus());}else{examPage.removeAttribute('inert');requestAnimationFrame(()=>questionCards[activeQuestion]?.focus());}}
async function enterFullscreen(){if(document.fullscreenElement||document.webkitFullscreenElement){setFullscreenGate(false);fullscreenStatus.textContent='Layar penuh aktif';return;}try{const el=document.documentElement;if(el.requestFullscreen){await el.requestFullscreen();}else if(el.webkitRequestFullscreen){await el.webkitRequestFullscreen();}setFullscreenGate(false);fullscreenStatus.textContent='Layar penuh aktif';}catch(err){setFullscreenGate(false);fullscreenStatus.textContent='Layar penuh tidak aktif';}}
function openSubmitModal(){if(submitted)return;if(modalAnsweredInfo)modalAnsweredInfo.textContent=answeredCount.textContent;if(modalTimerInfo)modalTimerInfo.textContent=timer.textContent;if(typeof submitConfirmModal.showModal==='function'){submitConfirmModal.showModal();}else{submitConfirmModal.setAttribute('open','');}}
function closeSubmitModal(){if(typeof submitConfirmModal.close==='function'){submitConfirmModal.close();}else{submitConfirmModal.removeAttribute('open');}}
if(modalCancelBtn)modalCancelBtn.addEventListener('click',closeSubmitModal);
if(modalCloseX)modalCloseX.addEventListener('click',closeSubmitModal);
if(modalConfirmSubmitBtn){modalConfirmSubmitBtn.addEventListener('click',()=>{submitted=true;closeSubmitModal();saveStatus.textContent='Mengumpulkan jawaban…';submitForm.submit();});}
tick();setInterval(tick,1000);updateProgress();showQuestion(activeQuestion);
questionNavigation.forEach(button=>button.addEventListener('click',()=>showQuestion(Number(button.dataset.questionIndex),true)));
previousQuestion.addEventListener('click',()=>showQuestion(activeQuestion-1,true));nextQuestion.addEventListener('click',()=>showQuestion(activeQuestion+1,true));
document.querySelectorAll('input[type=radio]').forEach(input=>input.addEventListener('change',async()=>{updateProgress();saveStatus.textContent='Menyimpan…';const body=new URLSearchParams({csrf,attempt_id:attemptId,question_id:input.dataset.questionId,selected_option:input.value});try{const response=await fetch(answerEndpoint,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});const payload=await response.json();saveStatus.textContent=payload.ok?'Tersimpan otomatis':'Gagal menyimpan';}catch{saveStatus.textContent='Gagal menyimpan; pilih jawaban sekali lagi.';}}));
document.addEventListener('visibilitychange',()=>{if(document.hidden)securityEvent('TAB_HIDDEN')});
document.addEventListener('fullscreenchange',()=>{const active=Boolean(document.fullscreenElement||document.webkitFullscreenElement);setFullscreenGate(!active);fullscreenStatus.textContent=active?'Layar penuh aktif':'Konfirmasi layar penuh diperlukan';securityEvent(active?'FULLSCREEN_ENTERED':'FULLSCREEN_EXIT');});
fullscreenRetry.addEventListener('click',async()=>{await enterFullscreen();setFullscreenGate(false);});
fullscreenGate.addEventListener('keydown',event=>{if(event.key==='Tab'){event.preventDefault();fullscreenRetry.focus();}});
window.addEventListener('load',enterFullscreen,{once:true});
const examSubmitBtn=document.querySelector('.exam-submit-button');
if(examSubmitBtn){examSubmitBtn.addEventListener('click',event=>{event.preventDefault();if(submitted)return;openSubmitModal();});}
submitForm.addEventListener('submit',event=>{if(!submitted){event.preventDefault();openSubmitModal();}});
</script></body></html>
