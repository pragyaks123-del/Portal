// Dev 2 Sprint 2: Automatic CV Scan - show scanning progress and prevent duplicate submissions.
// Uses fetch() so the animated progress bar stays visible for the full 30-second scan duration.
// A plain form POST navigates away immediately, killing the progress bar before the scan runs.
const scanForm = document.querySelector("[data-scan-form]");
const scanProgress = document.querySelector("[data-scan-progress]");

if (scanForm && scanProgress) {
  scanForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    // Show progress bar and lock the submit button.
    scanProgress.hidden = false;
<<<<<<< HEAD
=======

>>>>>>> 8c08e0c49f70475bd2ba411a366cfdec32451326
    const submitButtons = [
      ...scanForm.querySelectorAll('button[type="submit"]'),
    ];
    submitButtons.forEach((button) => {
      button.disabled = true;
      button.textContent = "Scanning...";
    });

    try {
      const response = await fetch(scanForm.action || window.location.href, {
        method: "POST",
        headers: { "X-CV-Scan-Ajax": "1" },
        body: new FormData(scanForm),
      });

      const json = await response.json();

      if (json.ok) {
        // Redirect so flash messages render normally after scan completes.
        window.location.href = json.redirect || window.location.href;
      } else {
        // Show error inline; progress bar hides and button re-enables.
        scanProgress.hidden = true;
        submitButtons.forEach((button) => {
          button.disabled = false;
          button.textContent = "Upload Resume";
        });
<<<<<<< HEAD
=======

>>>>>>> 8c08e0c49f70475bd2ba411a366cfdec32451326
        const errorEl = scanForm.querySelector("[data-scan-error]");
        if (errorEl) {
          errorEl.textContent =
            json.error || "Something went wrong. Please try again.";
          errorEl.hidden = false;
        }
      }
    } catch {
      // Network failure; fall back to a plain submit so the user is never left stuck.
      scanProgress.hidden = true;
      submitButtons.forEach((button) => {
        button.disabled = false;
        button.textContent = "Upload Resume";
      });
      scanForm.submit();
    }
  });
}
