const app = document.querySelector('.app');
const csrf = app?.dataset.csrf || '';
let conversationId = null, lastId = 0, poller = null, seen = new Set();
const messages = document.querySelector('#messages'), input = document.querySelector('#messageInput'), typing = document.querySelector('#typing');
const mediaInput = document.querySelector('#mediaInput'), attachButton = document.querySelector('#attachButton'), emojiButton = document.querySelector('#emojiButton'), emojiPicker = document.querySelector('#emojiPicker');
const esc = s => (s ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
const emoji = ['😀','😂','😊','😍','😎','😢','😡','👍','👎','👏','🙏','🔥','✨','🎉','❤️','💀','🤔','🙌','✅','🚀'];
function nearBottom(){return messages && messages.scrollHeight - messages.scrollTop - messages.clientHeight < 120;}
function formatBody(body){
  const parts = esc(body).split(/(\*\*[^*]+\*\*)/g);
  return parts.map(part => part.startsWith('**') && part.endsWith('**') ? `<strong>${part.slice(2,-2)}</strong>` : part).join('').replace(/\n/g,'<br>');
}
function mediaMarkup(m){
  if(!m.media_path) return '';
  const src = esc(m.media_path), name = esc(m.media_name || 'attachment');
  if(m.media_type === 'image') return `<a href="${src}" target="_blank" rel="noopener"><img class="message-media" src="${src}" alt="${name}"></a>`;
  if(m.media_type === 'video') return `<video class="message-media" src="${src}" controls preload="metadata"></video>`;
  return '';
}
function addMessage(m){ if(!messages || seen.has(+m.id)) return; seen.add(+m.id); const stick=nearBottom(); const mine=m.user_id==window.userId; const el=document.createElement('article'); el.className='msg'+(mine?' mine':''); el.innerHTML=`<div class="avatar">${esc(m.username[0]?.toUpperCase())}</div><div><div class="meta"><b>${esc(m.username)}</b><time>${esc(m.created_at)}</time></div><p>${formatBody(m.body)}</p>${mediaMarkup(m)}</div>`; messages.appendChild(el); lastId=Math.max(lastId,+m.id); if(stick) messages.scrollTo({top:messages.scrollHeight,behavior:'smooth'}); }
async function poll(){ if(!conversationId) return; const r=await fetch(`api/messages.php?conversation_id=${conversationId}&after_id=${lastId}`); const j=await r.json(); if(!j.ok) return; j.messages.forEach(addMessage); typing.innerHTML=j.typing.length?`${esc(j.typing.map(t=>t.username).join(', '))} ${j.typing.length>1?'are':'is'} typing<span></span><span></span><span></span>`:''; }
function selectConversation(id,title){ conversationId=id; lastId=0; seen.clear(); messages.innerHTML=''; document.querySelector('#chatTitle').textContent=title||'Direct message'; document.querySelector('#chatSubtitle').textContent='Live conversation'; clearInterval(poller); poll(); poller=setInterval(poll,1500); }
document.querySelectorAll('.conversation').forEach(b=>b.onclick=()=>selectConversation(b.dataset.id,b.textContent));
document.querySelectorAll('.friend').forEach(b=>b.onclick=async()=>{const fd=new FormData(); fd.append('action','start'); fd.append('friend_id',b.dataset.id); fd.append('csrf',csrf); const j=await (await fetch('api/friends.php',{method:'POST',body:fd})).json(); if(j.ok) selectConversation(j.conversation_id,b.textContent);});
const params = new URLSearchParams(location.search); if(params.has('friend')) document.querySelector(`.friend[data-id="${CSS.escape(params.get('friend'))}"]`)?.click();
document.querySelector('#messageForm')?.addEventListener('submit', async e=>{e.preventDefault(); if(!conversationId) return; const file = mediaInput?.files?.[0]; if(!input.value.trim() && !file) return; const fd=new FormData(); fd.append('conversation_id',conversationId); fd.append('body',input.value); if(file) fd.append('media', file); fd.append('csrf',csrf); input.value=''; if(mediaInput) mediaInput.value=''; await fetch('api/send_message.php',{method:'POST',body:fd}); poll();});
input?.addEventListener('keydown',e=>{ if(e.key==='Enter'&&!e.shiftKey){e.preventDefault(); document.querySelector('#messageForm').requestSubmit();}});
let typeTimer=0; input?.addEventListener('input',()=>{ if(!conversationId||Date.now()-typeTimer<1200) return; typeTimer=Date.now(); const fd=new FormData(); fd.append('conversation_id',conversationId); fd.append('csrf',csrf); fetch('api/typing.php',{method:'POST',body:fd});});
attachButton?.addEventListener('click',()=>mediaInput.click());
if(emojiPicker){ emojiPicker.innerHTML = emoji.map(e=>`<button type="button">${e}</button>`).join(''); emojiButton?.addEventListener('click',()=>emojiPicker.hidden=!emojiPicker.hidden); emojiPicker.querySelectorAll('button').forEach(b=>b.onclick=()=>{ input.value += b.textContent; input.focus(); emojiPicker.hidden=true; }); }
document.querySelector('#searchForm')?.addEventListener('submit', async e=>{e.preventDefault(); const q=e.target.q.value.trim(); const box=document.querySelector('#friendResults'); if(!q){box.innerHTML='';return;} const j=await (await fetch(`api/friends.php?action=search&q=${encodeURIComponent(q)}`)).json(); box.innerHTML=(j.users||[]).map(u=>`<div class="result">@${esc(u.username)} <button data-id="${u.id}">Add</button></div>`).join('') || '<p class="empty-note">No users found.</p>'; box.querySelectorAll('button').forEach(btn=>btn.onclick=async()=>{const fd=new FormData(); fd.append('action','request'); fd.append('user_id',btn.dataset.id); fd.append('csrf',csrf); await fetch('api/friends.php',{method:'POST',body:fd}); btn.textContent='Sent';});});
async function loadRequests(){ const box=document.querySelector('#requests'); if(!box) return; const j=await (await fetch('api/friends.php?action=list')).json(); box.innerHTML=(j.requests||[]).map(r=>`<div class="request">${esc(r.username)} wants to connect <button data-d="accept" data-id="${r.id}">Accept</button><button data-d="decline" data-id="${r.id}">Decline</button></div>`).join('') || '<p class="empty-note">No pending requests.</p>'; box.querySelectorAll('button').forEach(b=>b.onclick=async()=>{const fd=new FormData(); fd.append('action','respond'); fd.append('request_id',b.dataset.id); fd.append('decision',b.dataset.d); fd.append('csrf',csrf); await fetch('api/friends.php',{method:'POST',body:fd}); loadRequests();});}
loadRequests();
