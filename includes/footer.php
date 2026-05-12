    </div>
    <!-- End Main Content -->
    
    <footer class="bg-light py-3 mt-5 border-top">
        <div class="container text-center">
            <div class="mb-2">
                <span class="navbar-logo" style="width: 25px; height: 25px; display: inline-block;"></span>
            </div>
            <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo getSetting('court_name') ?: 'High Court of Ghana'; ?>. All rights reserved.</p>
        </div>
    </footer>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Custom JS -->
    <script src="assets/js/script.js"></script>
    
    <!-- Initialize DataTables -->
    <script>
        $(document).ready(function() {
            // Only initialize DataTables if the page doesn't have custom initialization
            if (!window.customDataTablesInit && $('.datatable').length > 0) {
                $('.datatable').DataTable({
                    responsive: true,
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: "Search...",
                    }
                });
            }
        });
    </script>
</body>
</html>
