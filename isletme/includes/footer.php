    </main> <!-- End biz-content -->
</div> <!-- End biz-main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Quill Rich Text Editor -->
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
    document.getElementById('sidebarToggle')?.addEventListener('click', function() {
        document.body.classList.toggle('sidebar-open');
    });

    // Quill editor init
    const quillEl = document.getElementById('quill-editor');
    if (quillEl) {
        const hiddenInput = document.getElementById('description-hidden');

        const quill = new Quill('#quill-editor', {
            theme: 'snow',
            placeholder: 'Kampanyanın şartları, içerikleri ve koşullarını yazın...',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline'],
                    [{ 'color': [] }],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                    ['clean']
                ]
            }
        });

        // Load existing content
        if (hiddenInput.value.trim()) {
            quill.root.innerHTML = hiddenInput.value;
        }

        // Before form submit, copy quill content to hidden textarea
        quillEl.closest('form')?.addEventListener('submit', function() {
            hiddenInput.value = quill.root.innerHTML;
        });
    }
</script>
</body>
</html>