  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Set default date (browser local date) for empty date inputs when the page loads
    document.addEventListener('DOMContentLoaded', function(){
      try {
        var today = new Date();
        var y = today.getFullYear();
        var m = String(today.getMonth()+1).padStart(2,'0');
        var d = String(today.getDate()).padStart(2,'0');
        var s = y + '-' + m + '-' + d;
        // Only set default on inputs explicitly marked with data-default-today
        document.querySelectorAll('input[type=\"date\"][data-default-today]').forEach(function(inp){
          if (!inp.value) inp.value = s;
        });
      } catch (e) {
        // fail silently
        console.error(e);
      }
    });
  </script>
  </body>
</html>
