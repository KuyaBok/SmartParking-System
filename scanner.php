<script src="https://unpkg.com/html5-qrcode"></script>

<div id="reader"></div>

<script>
const scanner = new Html5Qrcode("reader");

scanner.start(
  { facingMode: "environment" },
  { fps: 10, qrbox: 250 },
  (decodedText) => {
    window.location.href = decodedText;
  }
);
</script>
