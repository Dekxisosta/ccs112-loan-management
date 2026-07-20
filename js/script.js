
const API_URL = "api.php";


const state = {
  students: [], // all students, refreshed after every student change
  loans: [], // loans for the currently selected student (Loans tab)
  paymentLoans: [], // loans for the currently selected student (Payments tab)
  currentLoanId: null, // loan currently shown in Payments tab
};

// ---- element references -------------------------------------------------
const studentForm = document.getElementById("studentForm");
const submitBtn = document.getElementById("submitBtn");
const cancelBtn = document.getElementById("cancelBtn");
const studentTableBody = document.getElementById("studentTableBody");
const studentCount = document.getElementById("studentCount");

const loanForm = document.getElementById("loanForm");
const loanStudentSelect = document.getElementById("loanStudentSelect");
const loanTableBody = document.getElementById("loanTableBody");
const loanCount = document.getElementById("loanCount");
const loanFormCard = document.getElementById("loanFormCard");

const paymentForm = document.getElementById("paymentForm");
const paymentStudentSelect = document.getElementById("paymentStudentSelect");
const paymentLoanSelect = document.getElementById("paymentLoanSelect");
const paymentTableBody = document.getElementById("paymentTableBody");
const paymentCount = document.getElementById("paymentCount");
const paymentFormCard = document.getElementById("paymentFormCard");
const summaryCard = document.getElementById("summaryCard");

const toastEl = document.getElementById("toast");

// ===========================================================
// Helpers
// ===========================================================

function escapeHtml(value) {
  return String(value ?? "").replace(/[&<>"']/g, (ch) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;",
  })[ch]);
}

function formatCurrency(amount) {
  const n = Number(amount) || 0;
  return "\u20B1" + n.toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(dateStr) {
  if (!dateStr) return "";
  const d = new Date(dateStr);
  if (Number.isNaN(d.getTime())) return dateStr;
  return d.toLocaleDateString("en-PH", { year: "numeric", month: "short", day: "numeric" });
}

let toastTimer = null;
function showToast(message, isError = false) {
  toastEl.textContent = message;
  toastEl.classList.toggle("is-error", isError);
  toastEl.classList.add("is-visible");
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toastEl.classList.remove("is-visible"), 3000);
}

/**
 * Talk to api.php. Builds the ?entity=... query string, sends JSON bodies,
 * and surfaces the { error: "..." } payloads the backend returns.
 */
async function api(entity, method = "GET", { params = {}, body = null } = {}) {
  const query = new URLSearchParams({ entity, ...params });
  const options = { method, headers: {} };

  if (body !== null) {
    options.headers["Content-Type"] = "application/json";
    options.body = JSON.stringify(body);
  }

  let response;
  try {
    response = await fetch(`${API_URL}?${query.toString()}`, options);
  } catch (err) {
    throw new Error("Could not reach the server. Is the PHP backend running?");
  }

  let data = null;
  try {
    data = await response.json();
  } catch (err) {
    throw new Error("The server returned an unexpected response.");
  }

  if (!response.ok || data?.error) {
    throw new Error(data?.error || `Request failed (${response.status})`);
  }

  return data;
}

// ===========================================================
// Tab switching
// ===========================================================

document.getElementById("tabs").addEventListener("click", (e) => {
  const btn = e.target.closest(".tab-btn");
  if (!btn) return;
  activateTab(btn.dataset.tab);
});

function activateTab(tab) {
  document.querySelectorAll(".tab-btn").forEach((b) => b.classList.toggle("is-active", b.dataset.tab === tab));
  document.querySelectorAll(".panel").forEach((p) => p.classList.toggle("is-active", p.id === `tab-${tab}`));
}

// ===========================================================
// STUDENTS
// ===========================================================

async function fetchStudents() {
  try {
    state.students = await api("student", "GET");
    renderStudents();
    populateStudentDropdowns();
  } catch (err) {
    studentTableBody.innerHTML = `<tr class="empty-row"><td colspan="5">${escapeHtml(err.message)}</td></tr>`;
    showToast(err.message, true);
  }
}

function renderStudents() {
  studentCount.textContent = state.students.length;

  if (state.students.length === 0) {
    studentTableBody.innerHTML = `<tr class="empty-row"><td colspan="5">No students yet. Add one above to get started.</td></tr>`;
    return;
  }

  studentTableBody.innerHTML = state.students
    .map(
      (s) => `
      <tr>
        <td>${escapeHtml(s.id)}</td>
        <td>${escapeHtml(s.name)}</td>
        <td>${escapeHtml(s.email)}</td>
        <td>${escapeHtml(s.course)}</td>
        <td>
          <button class="btn-edit" data-action="edit-student" data-id="${s.id}">Edit</button>
          <button class="btn-delete" data-action="delete-student" data-id="${s.id}">Delete</button>
          <button class="btn-link" data-action="view-loans" data-id="${s.id}">Loans</button>
        </td>
      </tr>`
    )
    .join("");
}

studentTableBody.addEventListener("click", (e) => {
  const btn = e.target.closest("button[data-action]");
  if (!btn) return;
  const id = Number(btn.dataset.id);
  const student = state.students.find((s) => Number(s.id) === id);
  if (!student) return;

  if (btn.dataset.action === "edit-student") editStudent(student);
  if (btn.dataset.action === "delete-student") deleteStudent(id);
  if (btn.dataset.action === "view-loans") {
    activateTab("loans");
    loanStudentSelect.value = String(id);
    loanStudentSelect.dispatchEvent(new Event("change"));
  }
});

studentForm.addEventListener("submit", async (e) => {
  e.preventDefault();

  const id = document.getElementById("studentId").value;
  const payload = {
    name: document.getElementById("name").value.trim(),
    email: document.getElementById("email").value.trim(),
    course: document.getElementById("course").value.trim(),
  };

  let method = "POST";
  if (id) {
    payload.id = id;
    method = "PUT";
  }

  try {
    const result = await api("student", method, { body: payload });
    showToast(result.message || "Saved.");
    resetForm();
    await fetchStudents();
  } catch (err) {
    showToast(err.message, true);
  }
});

function editStudent(student) {
  document.getElementById("studentId").value = student.id;
  document.getElementById("name").value = student.name;
  document.getElementById("email").value = student.email;
  document.getElementById("course").value = student.course;

  submitBtn.textContent = "Update Student";
  cancelBtn.style.display = "inline-block";
  document.getElementById("name").focus();
}

async function deleteStudent(id) {
  if (!confirm("Delete this student? Their loans and payments will be removed too.")) return;
  try {
    const result = await api("student", "DELETE", { body: { id } });
    showToast(result.message || "Deleted.");
    await fetchStudents();
  } catch (err) {
    showToast(err.message, true);
  }
}

cancelBtn.addEventListener("click", resetForm);

function resetForm() {
  studentForm.reset();
  document.getElementById("studentId").value = "";
  submitBtn.textContent = "Add Student";
  cancelBtn.style.display = "none";
}

function populateStudentDropdowns() {
  const options =
    `<option value="">Choose a student&hellip;</option>` +
    state.students.map((s) => `<option value="${s.id}">${escapeHtml(s.name)} (#${s.id})</option>`).join("");

  const prevLoan = loanStudentSelect.value;
  const prevPayment = paymentStudentSelect.value;

  loanStudentSelect.innerHTML = options;
  paymentStudentSelect.innerHTML = options;

  if (prevLoan && state.students.some((s) => String(s.id) === prevLoan)) loanStudentSelect.value = prevLoan;
  if (prevPayment && state.students.some((s) => String(s.id) === prevPayment)) paymentStudentSelect.value = prevPayment;
}

// ===========================================================
// LOANS
// ===========================================================

loanStudentSelect.addEventListener("change", async () => {
  const studentId = loanStudentSelect.value;
  if (!studentId) {
    state.loans = [];
    renderLoans();
    return;
  }
  await fetchLoans(studentId);
});

async function fetchLoans(studentId) {
  loanTableBody.innerHTML = `<tr class="empty-row"><td colspan="5">Loading loans&hellip;</td></tr>`;
  try {
    state.loans = await api("loan", "GET", { params: { student_id: studentId } });
    renderLoans();
  } catch (err) {
    state.loans = [];
    loanTableBody.innerHTML = `<tr class="empty-row"><td colspan="5">${escapeHtml(err.message)}</td></tr>`;
  }
}

function statusPillClass(status) {
  return (
    {
      Pending: "status-pending",
      Approved: "status-approved",
      Disbursed: "status-disbursed",
    }[status] || "status-pending"
  );
}

function renderLoans() {
  loanCount.textContent = state.loans.length;

  if (!loanStudentSelect.value) {
    loanTableBody.innerHTML = `<tr class="empty-row"><td colspan="5">Select a student to view loans.</td></tr>`;
    return;
  }

  if (state.loans.length === 0) {
    loanTableBody.innerHTML = `<tr class="empty-row"><td colspan="5">No loans recorded for this student yet.</td></tr>`;
    return;
  }

  loanTableBody.innerHTML = state.loans
    .map(
      (l) => `
      <tr>
        <td>${escapeHtml(l.id)}</td>
        <td>${formatCurrency(l.amount)}</td>
        <td>${escapeHtml(l.loan_type)}</td>
        <td><span class="status-pill ${statusPillClass(l.status)}">${escapeHtml(l.status)}</span></td>
        <td>
          <button class="btn-link" data-action="view-payments" data-id="${l.id}" data-student="${loanStudentSelect.value}">
            Record / View Payments
          </button>
        </td>
      </tr>`
    )
    .join("");
}

loanTableBody.addEventListener("click", (e) => {
  const btn = e.target.closest("button[data-action='view-payments']");
  if (!btn) return;
  activateTab("payments");
  paymentStudentSelect.value = btn.dataset.student;
  paymentStudentSelect.dispatchEvent(new Event("change", { targetLoanId: btn.dataset.id }));
  // fetchLoans for the payments dropdown, then select this loan once populated
  loadPaymentLoans(btn.dataset.student).then(() => {
    paymentLoanSelect.value = btn.dataset.id;
    paymentLoanSelect.dispatchEvent(new Event("change"));
  });
});

loanForm.addEventListener("submit", async (e) => {
  e.preventDefault();

  const studentId = loanStudentSelect.value;
  if (!studentId) {
    showToast("Choose a student first.", true);
    return;
  }

  const payload = {
    student_id: studentId,
    amount: document.getElementById("loanAmount").value,
    loan_type: document.getElementById("loanType").value,
    status: document.getElementById("loanStatus").value,
  };

  try {
    const result = await api("loan", "POST", { body: payload });
    showToast(result.message || "Loan added.");
    loanForm.reset();
    document.getElementById("loanStatus").value = "Pending";
    await fetchLoans(studentId);
  } catch (err) {
    showToast(err.message, true);
  }
});

// ===========================================================
// PAYMENTS
// ===========================================================

paymentStudentSelect.addEventListener("change", async () => {
  const studentId = paymentStudentSelect.value;
  resetPaymentView();
  if (!studentId) {
    paymentLoanSelect.disabled = true;
    paymentLoanSelect.innerHTML = `<option value="">Choose a student first&hellip;</option>`;
    return;
  }
  await loadPaymentLoans(studentId);
});

async function loadPaymentLoans(studentId) {
  paymentLoanSelect.disabled = true;
  paymentLoanSelect.innerHTML = `<option value="">Loading loans&hellip;</option>`;
  try {
    state.paymentLoans = await api("loan", "GET", { params: { student_id: studentId } });
    if (state.paymentLoans.length === 0) {
      paymentLoanSelect.innerHTML = `<option value="">No loans for this student</option>`;
      return;
    }
    paymentLoanSelect.disabled = false;
    paymentLoanSelect.innerHTML =
      `<option value="">Choose a loan&hellip;</option>` +
      state.paymentLoans
        .map((l) => `<option value="${l.id}">#${l.id} &middot; ${escapeHtml(l.loan_type)} &middot; ${formatCurrency(l.amount)}</option>`)
        .join("");
  } catch (err) {
    paymentLoanSelect.innerHTML = `<option value="">Could not load loans</option>`;
    showToast(err.message, true);
  }
}

paymentLoanSelect.addEventListener("change", async () => {
  const loanId = paymentLoanSelect.value;
  state.currentLoanId = loanId || null;
  if (!loanId) {
    resetPaymentView();
    return;
  }
  await fetchPayments(loanId);
});

async function fetchPayments(loanId) {
  paymentTableBody.innerHTML = `<tr class="empty-row"><td colspan="4">Loading payments&hellip;</td></tr>`;
  try {
    const data = await api("payment", "GET", { params: { loan_id: loanId } });
    renderPayments(data.payments || []);
    renderSummary(loanId, data.summary);
  } catch (err) {
    paymentTableBody.innerHTML = `<tr class="empty-row"><td colspan="4">${escapeHtml(err.message)}</td></tr>`;
    summaryCard.hidden = true;
  }
}

function renderPayments(payments) {
  paymentCount.textContent = payments.length;

  if (payments.length === 0) {
    paymentTableBody.innerHTML = `<tr class="empty-row"><td colspan="4">No payments recorded for this loan yet.</td></tr>`;
    return;
  }

  paymentTableBody.innerHTML = payments
    .map(
      (p) => `
      <tr>
        <td>${escapeHtml(p.id)}</td>
        <td>${formatCurrency(p.payment_amount)}</td>
        <td>${formatDate(p.payment_date)}</td>
        <td>${escapeHtml(p.payment_method)}</td>
      </tr>`
    )
    .join("");
}

function renderSummary(loanId, summary) {
  const loan = state.paymentLoans.find((l) => String(l.id) === String(loanId));
  const loanAmount = loan ? Number(loan.amount) : 0;
  const totalPaid = summary ? Number(summary.total_paid) : 0;
  const remaining = summary ? Number(summary.remaining_balance) : loanAmount;
  const pct = loanAmount > 0 ? Math.min(100, Math.max(0, (totalPaid / loanAmount) * 100)) : 0;

  document.getElementById("summaryLoanAmount").textContent = formatCurrency(loanAmount);
  document.getElementById("summaryPaid").textContent = formatCurrency(totalPaid);
  document.getElementById("summaryBalance").textContent = formatCurrency(remaining);
  document.getElementById("progressFill").style.width = `${pct}%`;
  document.getElementById("progressPct").textContent = `${pct.toFixed(1)}% repaid`;

  summaryCard.hidden = false;
}

function resetPaymentView() {
  summaryCard.hidden = true;
  paymentCount.textContent = "0";
  paymentTableBody.innerHTML = `<tr class="empty-row"><td colspan="4">Select a loan to view payments.</td></tr>`;
}

paymentForm.addEventListener("submit", async (e) => {
  e.preventDefault();

  const loanId = paymentLoanSelect.value;
  if (!loanId) {
    showToast("Choose a loan first.", true);
    return;
  }

  const payload = {
    loan_id: loanId,
    payment_amount: document.getElementById("paymentAmount").value,
    payment_date: document.getElementById("paymentDate").value,
    payment_method: document.getElementById("paymentMethod").value,
  };

  try {
    const result = await api("payment", "POST", { body: payload });
    showToast(result.message || "Payment recorded.");
    paymentForm.reset();
    await fetchPayments(loanId);
  } catch (err) {
    showToast(err.message, true);
  }
});



document.addEventListener("DOMContentLoaded", () => {
  fetchStudents();
});
