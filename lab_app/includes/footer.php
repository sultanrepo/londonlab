</div><!-- /page-content -->
</div><!-- /main-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
$(document).ready(function(){
    if ($.fn.DataTable) {
        $('.dt-table').each(function() {
            if (!$.fn.DataTable.isDataTable(this)) {
                $(this).DataTable({
                    pageLength: 15,
                    language: { search: '', searchPlaceholder: '🔍 Search...' },
                    dom: '<"row mb-3"<"col-sm-6"l><"col-sm-6 text-end"f>>rt<"row mt-3"<"col-sm-6"i><"col-sm-6"p>>',
                    columnDefs: [{ targets: '_all', defaultContent: '' }],
                });
            }
        });
    }
    $('[data-bs-toggle="tooltip"]').each(function(){ new bootstrap.Tooltip(this); });
});
function confirmDelete(url, msg) {
    if (confirm(msg || 'Are you sure?')) window.location.href = url;
}
</script>
<?php if (isset($extraJs)) echo $extraJs; ?>
</body>
</html>
<?php ob_end_flush(); ?>