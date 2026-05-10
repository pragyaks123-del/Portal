// Dev 2 Sprint 2: Automatic CV Scan - show scanning progress and prevent duplicate submissions.
const scanForm = document.querySelector("[data-scan-form]");
const scanProgress = document.querySelector("[data-scan-progress]");

if (scanForm && scanProgress) {
  scanForm.addEventListener("submit", () => {
    scanProgress.hidden = false;
    scanForm.querySelectorAll('button[type="submit"]').forEach((button) => {
      button.disabled = true;
      button.textContent = "Scanning...";
    });
  });
}
