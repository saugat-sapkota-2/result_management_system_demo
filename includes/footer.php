</div>
        </main>
        <footer class="app-footer">
            <div class="container-fluid text-center text-muted small">
                &copy; <?php echo currentBsYear(); ?> BS (<?php echo date('Y'); ?>) <?php echo e(get_setting('institute_name', APP_NAME)); ?> — BCA 4<sup>th</sup> Semester Project
            </div>
        </footer>
    </div>
</div>
<script src="<?php echo e(url('assets/vendor/bootstrap/bootstrap.bundle.min.js')); ?>"></script>
<script src="<?php echo e(url('assets/js/app.js')); ?>"></script>
</body>
</html>