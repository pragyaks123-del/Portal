// Automatic CV Scan - show scanning progress and prevent duplicate submissions.
// Uses fetch() so the animated progress bar stays visible for the full 30-second scan duration.
// A plain form POST navigates away immediately, killing the progress bar before the scan runs.
const scanForm = document.querySelector("[data-scan-form]");
const scanProgress = document.querySelector("[data-scan-progress]");
// form and progress bar existing before attaching event listene
if (scanForm && scanProgress) {
  scanForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    // Show progress bar
    scanProgress.hidden = false;

    // Disable submit buttons to prevent duplicate submissions
    const submitButtons = [
      ...scanForm.querySelectorAll('button[type="submit"]'),
    ];
    submitButtons.forEach((button) => {
      button.disabled = true;
      button.textContent = "Scanning...";
    });

    try {
      // Send form data via AJAX to keep the user on the page and show progress.
      const response = await fetch(scanForm.action || window.location.href, {
        method: "POST",
        headers: { "X-CV-Scan-Ajax": "1" },
        body: new FormData(scanForm),
      });
      //server response for JSOn
      const json = await response.json();

      if (json.ok) {
        // IF scan sucessfully redirect user result page
        window.location.href = json.redirect || window.location.href;
      } else {
        // If backend returns error, show message and reset UI state
        scanProgress.hidden = true;
        submitButtons.forEach((button) => {
          button.disabled = false;
          button.textContent = "Upload Resume";
        });

        // Display error message inside form if error container exists
        const errorEl = scanForm.querySelector("[data-scan-error]");
        if (errorEl) {
          errorEl.textContent =
            json.error || "Something went wrong. Please try again.";
          errorEl.hidden = false;
        }
      }
    } catch {
      // Network failure — fall back to a plain submit so the user is never left stuck.
      scanProgress.hidden = true;
      submitButtons.forEach((button) => {
        button.disabled = false;
        button.textContent = "Upload Resume";
      });
      scanForm.submit();
    }
  });
}
