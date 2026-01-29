<?php
require_once '../includes/auth.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

$departmentCode = $_GET['department_code'] ?? 'hr3_budget';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>HR3 Budget Allocation</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
<div class="max-w-5xl mx-auto bg-white rounded shadow p-6">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-bold">Claims Summary – <?php echo htmlspecialchars($departmentCode); ?></h1>
    <div class="flex items-center gap-2">
      <label class="text-sm text-gray-600">Department</label>
      <input id="deptInput" class="border rounded px-2 py-1 text-sm" value="<?php echo htmlspecialchars($departmentCode); ?>">
      <button class="px-3 py-1 bg-gray-800 text-white rounded text-sm" onclick="reloadDept()">Load</button>
    </div>
  </div>

  <table class="w-full border-collapse">
    <thead>
      <tr class="bg-gray-200 text-left">
        <th class="p-3">Period</th>
        <th class="p-3">Total</th>
        <th class="p-3">Allocated</th>
        <th class="p-3">Spent</th>
        <th class="p-3">Remaining</th>
        <th class="p-3 text-center">Actions</th>
      </tr>
    </thead>
    <tbody id="periodRows">
      <tr>
        <td colspan="6" class="p-3 text-center text-gray-500">Loading…</td>
      </tr>
    </tbody>
  </table>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="fixed inset-0 hidden items-center justify-center bg-black bg-opacity-50 z-50">
  <div class="bg-white rounded-lg shadow-lg w-96 p-6">
    <h2 class="text-lg font-bold mb-4">Are you sure?</h2>
    <p id="deleteMessage" class="mb-6 text-gray-600"></p>
    <div class="flex justify-end space-x-3">
      <button onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
      <button onclick="confirmDelete()" class="px-4 py-2 bg-red-600 text-white rounded">Delete</button>
    </div>
  </div>
 </div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 hidden bg-black bg-opacity-50 items-center justify-center z-50">
  <div class="bg-white rounded-lg p-6 w-96">
    <h2 class="text-lg font-bold mb-4">Edit Budget</h2>
    <input type="hidden" id="editDept">
    <input type="hidden" id="editPeriod">
    <label class="block mb-2">Total</label>
    <input id="editTotal" class="w-full border p-2 mb-3">
    <label class="block mb-2">Allocated</label>
    <input id="editAllocated" class="w-full border p-2 mb-3">
    <label class="block mb-2">Spent</label>
    <input id="editSpent" class="w-full border p-2 mb-4">
    <div class="flex justify-end gap-2">
      <button onclick="closeEditModal()" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
      <button onclick="saveEdit()" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
    </div>
  </div>
</div>

<script>
const apiBase = '../api/hr/claims_summary.php';
let deleteDept = '';
let deletePeriod = '';

function reloadDept() {
  const dept = document.getElementById('deptInput').value || 'hr3_budget';
  const url = new URL(window.location.href);
  url.searchParams.set('department_code', dept);
  window.location.href = url.toString();
}

async function loadData() {
  const dept = document.getElementById('deptInput').value || 'hr3_budget';
  const res = await fetch(`${apiBase}?department_code=${encodeURIComponent(dept)}`);
  const payload = await res.json();
  const data = payload.data || {};
  const periods = data.periods || [];
  const tbody = document.getElementById('periodRows');
  if (!Array.isArray(periods) || periods.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" class="p-3 text-center text-gray-500">No data</td></tr>';
    return;
  }
  tbody.innerHTML = periods.map(r => `
    <tr class="border-b hover:bg-gray-50">
      <td class="p-3 capitalize">${r.period}</td>
      <td class="p-3">${Number(r.total_budget || 0).toLocaleString()}</td>
      <td class="p-3">${Number(r.allocated || 0).toLocaleString()}</td>
      <td class="p-3">${Number(r.spent || 0).toLocaleString()}</td>
      <td class="p-3">${Number(r.remaining || 0).toLocaleString()}</td>
      <td class="p-3 flex gap-2 justify-center">
        <button class="p-2 bg-gray-800 hover:bg-gray-900 text-white rounded"
          onclick="openEditModal('${dept}','${r.period}',${r.total_budget || 0},${r.allocated || 0},${r.spent || 0})">✏️</button>
        <button class="p-2 bg-red-500 hover:bg-red-600 text-white rounded"
          onclick="openDeleteModal('${dept}','${r.period}')">🗑️</button>
      </td>
    </tr>
  `).join('');
}

function openDeleteModal(dept, period) {
  deleteDept = dept;
  deletePeriod = period;
  document.getElementById('deleteMessage').textContent = `Delete ${period} record? This cannot be undone.`;
  document.getElementById('deleteModal').classList.remove('hidden');
  document.getElementById('deleteModal').classList.add('flex');
}

function closeDeleteModal() {
  document.getElementById('deleteModal').classList.add('hidden');
  document.getElementById('deleteModal').classList.remove('flex');
}

function confirmDelete() {
  fetch(`${apiBase}?department_code=${encodeURIComponent(deleteDept)}&period=${encodeURIComponent(deletePeriod)}`, {
    method: 'DELETE'
  }).then(() => location.reload());
}

function openEditModal(dept, period, total, allocated, spent) {
  document.getElementById('editDept').value = dept;
  document.getElementById('editPeriod').value = period;
  document.getElementById('editTotal').value = total;
  document.getElementById('editAllocated').value = allocated;
  document.getElementById('editSpent').value = spent;
  document.getElementById('editModal').classList.remove('hidden');
  document.getElementById('editModal').classList.add('flex');
}

function closeEditModal() {
  document.getElementById('editModal').classList.add('hidden');
  document.getElementById('editModal').classList.remove('flex');
}

function saveEdit() {
  fetch(apiBase, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      department_code: document.getElementById('editDept').value,
      period: document.getElementById('editPeriod').value,
      total_budget: document.getElementById('editTotal').value,
      allocated: document.getElementById('editAllocated').value,
      spent: document.getElementById('editSpent').value
    })
  }).then(() => location.reload());
}

loadData();
</script>
</body>
</html>
