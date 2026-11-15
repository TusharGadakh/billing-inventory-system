        </div> <!-- /.content-wrapper -->

        <footer class="main-footer">
            <div class="footer-left">
                &copy; <?= date('Y') ?> <strong>Billing & Inventory Management System</strong>. Built with PHP & MySQL.
            </div>
            <div class="footer-right">
                <span>Fast &bull; Reliable &bull; Real-time Stock Tracking</span>
            </div>
        </footer>
    </main>
</div>

<!-- Core JavaScript -->
<script src="assets/js/main.js"></script>
<?php if (isset($extra_js)): ?>
    <script src="<?= e($extra_js) ?>"></script>
<?php endif; ?>
</body>
</html>
