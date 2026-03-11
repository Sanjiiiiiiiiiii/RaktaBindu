// Login
loginForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const data = new FormData(loginForm);
  data.append('action', 'login');

  const res = await fetch('login.php', { method: 'POST', body: data });
  const result = await res.json();
  alert(result.message);
  if (result.success) window.location.href = 'index.html';
});

// Send Code
sendCodeBtn.addEventListener('click', async () => {
  const email = resetEmail.value.trim();
  const data = new FormData();
  data.append('action', 'send_code');
  data.append('email', email);

  const res = await fetch('login.php', { method: 'POST', body: data });
  const result = await res.json();
  statusMsg.textContent = result.message;
  if (result.success) codeSection.style.display = 'block';
});

// Verify Code
verifyCodeBtn.addEventListener('click', async () => {
  const code = verificationCode.value;
  const data = new FormData();
  data.append('action', 'verify_code');
  data.append('code', code);

  const res = await fetch('login.php', { method: 'POST', body: data });
  const result = await res.json();
  statusMsg.textContent = result.message;
  if (result.success) window.location.href = 'reset-password.html';
});