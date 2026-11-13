<?php
require_once __DIR__ . '/_common.php';

require_admin();

$pdo = db();
$msg = '';
$edit = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf($_POST['csrf'] ?? null);

        $action = $_POST['action'] ?? '';

        if ($action === 'save') {

            $id = (int)($_POST['id'] ?? 0);

            $title = trim($_POST['title'] ?? '');
            $body = trim($_POST['body'] ?? '');
            $published = (int)($_POST['published'] ?? 0);

            if ($title === '') {
                throw new Exception('El título es obligatorio.');
            }

            if ($body === '') {
                throw new Exception('El contenido es obligatorio.');
            }

            if ($id) {

                $s = $pdo->prepare(
                    'UPDATE news
                     SET title = ?, body = ?, published = ?,
                         published_at = IF(?, published_at, NOW())
                     WHERE id = ?'
                );

                $s->execute([
                    $title,
                    $body,
                    $published,
                    $published,
                    $id
                ]);

            } else {

                $s = $pdo->prepare(
                    'INSERT INTO news
                     (title, body, published, published_at)
                     VALUES (?, ?, ?, NOW())'
                );

                $s->execute([
                    $title,
                    $body,
                    $published
                ]);
            }

            $msg = 'Noticia guardada correctamente.';

        } elseif ($action === 'delete') {

            $s = $pdo->prepare(
                'DELETE FROM news WHERE id = ?'
            );

            $s->execute([
                (int)$_POST['id']
            ]);

            $msg = 'Noticia eliminada.';
        }

    } catch (Throwable $e) {

        $msg = 'Error: ' . $e->getMessage();
    }
}

if (isset($_GET['edit'])) {

    $s = $pdo->prepare(
        'SELECT * FROM news WHERE id = ?'
    );

    $s->execute([
        (int)$_GET['edit']
    ]);

    $edit = $s->fetch();
}

$rows = $pdo->query(
    'SELECT * FROM news
     ORDER BY published_at DESC, id DESC'
)->fetchAll();

admin_header('Noticias', 'news');
?>

<style>
.news-editor-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 10px;
}

.news-editor-toolbar button {
    border: 1px solid rgba(255,255,255,.15);
    background: rgba(255,255,255,.06);
    color: inherit;
    border-radius: 6px;
    padding: 7px 11px;
    cursor: pointer;
    font-size: 14px;
}

.news-editor-toolbar button:hover {
    background: rgba(255,255,255,.12);
}

.news-editor-toolbar .separator {
    width: 1px;
    background: rgba(255,255,255,.12);
    margin: 0 3px;
}

.news-help {
    margin-top: 8px;
    font-size: 13px;
    opacity: .7;
}

.news-preview {
    margin-top: 20px;
    padding: 18px;
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 8px;
    background: rgba(0,0,0,.12);
}

.news-preview-title {
    font-size: 13px;
    text-transform: uppercase;
    opacity: .65;
    margin-bottom: 10px;
}

.news-preview-content {
    line-height: 1.7;
}

.news-preview-content a {
    color: #6eb5ff;
    text-decoration: underline;
}

.news-preview-content strong {
    font-weight: 700;
}

.news-preview-content em {
    font-style: italic;
}
</style>

<div class="eyebrow">NEXUS · COMUNICACIÓN</div>

<h1>Noticias</h1>

<?php if ($msg): ?>

    <div class="flash">
        <?=e($msg)?>
    </div>

<?php endif; ?>


<div class="admin-card">

    <h2>
        <?=$edit ? 'Editar noticia' : 'Nueva noticia'?>
    </h2>

    <form
        class="admin-form"
        method="post"
        id="newsForm"
    >

        <input
            type="hidden"
            name="csrf"
            value="<?=e(csrf_token())?>"
        >

        <input
            type="hidden"
            name="action"
            value="save"
        >

        <input
            type="hidden"
            name="id"
            value="<?=e((string)($edit['id'] ?? 0))?>"
        >


        <label>
            Título

            <input
                name="title"
                value="<?=e($edit['title'] ?? '')?>"
                required
            >
        </label>


        <label>
            Contenido
        </label>


        <div
            class="news-editor-toolbar"
            aria-label="Herramientas de edición"
        >

            <button
                type="button"
                onclick="formatNews('bold')"
                title="Negrita"
            >
                <strong>B</strong>
            </button>


            <button
                type="button"
                onclick="formatNews('italic')"
                title="Cursiva"
            >
                <em>I</em>
            </button>


            <span class="separator"></span>


            <button
                type="button"
                onclick="insertLink()"
                title="Insertar enlace"
            >
                🔗 Enlace
            </button>


            <button
                type="button"
                onclick="insertBreak()"
                title="Insertar salto de línea"
            >
                ↵ Salto
            </button>


            <span class="separator"></span>


            <button
                type="button"
                onclick="showNewsPreview()"
                title="Vista previa"
            >
                👁 Vista previa
            </button>

        </div>


        <textarea
            name="body"
            id="newsBody"
            rows="16"
            required
        ><?=e($edit['body'] ?? '')?></textarea>


        <div class="news-help">
            Puedes escribir normalmente y utilizar los botones para añadir
            formato y enlaces.
        </div>


        <div
            class="news-preview"
            id="newsPreview"
            style="display:none"
        >

            <div class="news-preview-title">
                Vista previa
            </div>

            <div
                class="news-preview-content"
                id="newsPreviewContent"
            ></div>

        </div>


        <label>

            <span>Publicar</span>

            <select name="published">

                <option
                    value="1"
                    <?=((int)($edit['published'] ?? 1) === 1 ? 'selected' : '')?>
                >
                    Sí
                </option>

                <option
                    value="0"
                    <?=((int)($edit['published'] ?? 1) === 0 ? 'selected' : '')?>
                >
                    No, borrador
                </option>

            </select>

        </label>


        <div class="admin-actions">

            <button
                class="btn primary"
                type="submit"
            >
                Guardar noticia
            </button>


            <?php if ($edit): ?>

                <a
                    class="btn ghost"
                    href="/Admin/news.php"
                >
                    Cancelar
                </a>

            <?php endif; ?>

        </div>

    </form>

</div>


<br>


<div class="admin-card">

    <h2>Noticias</h2>

    <div style="overflow:auto">

        <table class="admin-table">

            <thead>

                <tr>
                    <th>Fecha</th>
                    <th>Título</th>
                    <th>Estado</th>
                    <th></th>
                </tr>

            </thead>

            <tbody>

            <?php foreach ($rows as $n): ?>

                <tr>

                    <td>
                        <?=e(date('d/m/Y', strtotime($n['published_at'])))?>
                    </td>

                    <td>
                        <?=e($n['title'])?>
                    </td>

                    <td>
                        <?=$n['published'] ? 'Publicado' : 'Borrador'?>
                    </td>

                    <td>

                        <a href="?edit=<?=$n['id']?>">
                            Editar
                        </a>

                        <form
                            method="post"
                            style="display:inline"
                            onsubmit="return confirm('¿Eliminar noticia?')"
                        >

                            <input
                                type="hidden"
                                name="csrf"
                                value="<?=e(csrf_token())?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="delete"
                            >

                            <input
                                type="hidden"
                                name="id"
                                value="<?=$n['id']?>"
                            >

                            <button
                                class="btn danger"
                                style="padding:5px 9px"
                                type="submit"
                            >
                                Eliminar
                            </button>

                        </form>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>


<script>
(function () {

    const textarea = document.getElementById('newsBody');

    window.formatNews = function (type) {

        if (!textarea) return;

        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;

        const selected = textarea.value.substring(start, end);

        if (!selected) {
            alert('Selecciona primero el texto que quieres formatear.');
            return;
        }

        let replacement = selected;

        if (type === 'bold') {
            replacement = '<strong>' + selected + '</strong>';
        }

        if (type === 'italic') {
            replacement = '<em>' + selected + '</em>';
        }

        textarea.setRangeText(
            replacement,
            start,
            end,
            'select'
        );

        textarea.focus();
    };


    window.insertLink = function () {

        if (!textarea) return;

        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;

        const selected = textarea.value.substring(start, end);

        const linkText = prompt(
            'Texto que se mostrará como enlace:',
            selected || 'Leer más'
        );

        if (linkText === null) return;

        const url = prompt(
            'URL del enlace:',
            'https://'
        );

        if (url === null || url.trim() === '') return;

        const cleanUrl = url.trim();

        if (
            !/^https?:\/\//i.test(cleanUrl)
        ) {
            alert(
                'La URL debe comenzar por http:// o https://'
            );
            return;
        }

        const link =
            '<a href="' +
            escapeAttribute(cleanUrl) +
            '" target="_blank" rel="noopener noreferrer">' +
            escapeHtml(linkText) +
            '</a>';

        textarea.setRangeText(
            link,
            start,
            end,
            'end'
        );

        textarea.focus();
    };


    window.insertBreak = function () {

        if (!textarea) return;

        const position = textarea.selectionStart;

        textarea.setRangeText(
            '<br>',
            position,
            textarea.selectionEnd,
            'end'
        );

        textarea.focus();
    };


    window.showNewsPreview = function () {

        const preview = document.getElementById(
            'newsPreview'
        );

        const content = document.getElementById(
            'newsPreviewContent'
        );

        if (!preview || !content) return;

        const html = sanitizePreview(
            textarea.value
        );

        content.innerHTML = html;

        preview.style.display =
            preview.style.display === 'none'
                ? 'block'
                : 'none';
    };


    function escapeHtml(value) {

        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }


    function escapeAttribute(value) {

        return escapeHtml(value);
    }


    function sanitizePreview(html) {

        const template =
            document.createElement('template');

        template.innerHTML = html;

        const allowed = [
            'A',
            'STRONG',
            'EM',
            'B',
            'I',
            'BR',
            'P',
            'UL',
            'OL',
            'LI'
        ];

        template.content
            .querySelectorAll('*')
            .forEach(function (element) {

                if (!allowed.includes(element.tagName)) {
                    element.replaceWith(
                        document.createTextNode(
                            element.textContent
                        )
                    );
                    return;
                }

                Array.from(element.attributes)
                    .forEach(function (attribute) {

                        const name =
                            attribute.name.toLowerCase();

                        if (
                            element.tagName === 'A' &&
                            name === 'href'
                        ) {

                            if (
                                !/^https?:\/\//i.test(
                                    attribute.value
                                )
                            ) {
                                element.removeAttribute(
                                    'href'
                                );
                            }

                        } else if (
                            element.tagName === 'A' &&
                            (
                                name === 'target' ||
                                name === 'rel'
                            )
                        ) {

                            // Permitidos.

                        } else {

                            element.removeAttribute(
                                attribute.name
                            );
                        }
                    });
            });

        return template.innerHTML;
    }

})();
</script>


<?php admin_footer(); ?>