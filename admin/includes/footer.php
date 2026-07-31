</div> <!-- content-body close -->
</div> <!-- main-content close -->
</div> <!-- admin-wrapper close -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Quill Rich Text Editor -->
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
function initQuill(editorId, hiddenId) {
    const editorEl = document.getElementById(editorId);
    if (!editorEl) return;
    const hiddenInput = document.getElementById(hiddenId);
    const quill = new Quill('#' + editorId, {
        theme: 'snow',
        placeholder: 'Detaylı açıklama girin...',
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
    if (hiddenInput && hiddenInput.value.trim()) {
        quill.root.innerHTML = hiddenInput.value;
    }
    editorEl.closest('form')?.addEventListener('submit', function() {
        hiddenInput.value = quill.root.innerHTML;
    });
}
initQuill('quill-editor-camp', 'description-hidden-camp');
initQuill('quill-editor-ev',   'description-hidden-ev');
</script>
</body>
</html>
