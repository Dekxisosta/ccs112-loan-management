// Backend API endpoint
const API_URL = "api.php";

// Get references to form and buttons
const form = document.getElementById("studentForm");
const submitBtn = document.getElementById("submitBtn");
const cancelBtn = document.getElementById("cancelBtn");

// Load students when page loads
document.addEventListener("DOMContentLoaded", fetchStudents);

// Fetch all students
async function fetchStudents() {
  const response = await fetch(API_URL);
  const students = await response.json();

  const tbody = document.getElementById("studentTableBody");
  tbody.innerHTML = "";

  students.forEach((student) => {
    tbody.innerHTML += `
            <tr>
                <td>${student.id}</td>
                <td>${student.name}</td>
                <td>${student.email}</td>
                <td>${student.course}</td>
                <td>
                    <button class="btn-edit"
                        onclick="editStudent(${student.id}, '${student.name}', '${student.email}', '${student.course}')">
                        Edit
                    </button>

                    <button class="btn-delete"
                        onclick="deleteStudent(${student.id})">
                        Delete
                    </button>
                </td>
            </tr>
        `;
  });
}

// Handle Create and Update
form.addEventListener("submit", async (e) => {
  e.preventDefault();

  const id = document.getElementById("studentId").value;
  const name = document.getElementById("name").value;
  const email = document.getElementById("email").value;
  const course = document.getElementById("course").value;

  const payload = {
    name,
    email,
    course,
  };

  let method = "POST";

  if (id) {
    payload.id = id;
    method = "PUT";
  }

  const response = await fetch(API_URL, {
    method: method,
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(payload),
  });

  const result = await response.json();

  alert(result.message || result.error);

  resetForm();
  fetchStudents();
});

// Fill form for editing
function editStudent(id, name, email, course) {
  document.getElementById("studentId").value = id;
  document.getElementById("name").value = name;
  document.getElementById("email").value = email;
  document.getElementById("course").value = course;

  submitBtn.innerText = "Update Student";
  submitBtn.style.backgroundColor = "#ffc107";
  submitBtn.style.color = "black";

  cancelBtn.style.display = "inline-block";
}

// Delete student
async function deleteStudent(id) {
  if (confirm("Are you sure you want to delete this student?")) {
    const response = await fetch(API_URL, {
      method: "DELETE",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ id }),
    });

    const result = await response.json();

    alert(result.message || result.error);

    fetchStudents();
  }
}

// Reset form
function resetForm() {
  form.reset();

  document.getElementById("studentId").value = "";

  submitBtn.innerText = "Add Student";
  submitBtn.style.backgroundColor = "#28a745";
  submitBtn.style.color = "white";

  cancelBtn.style.display = "none";
}
