<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

/**
 * @return array{title:string,slug:string,body:string,status:string}
 */
function admin_fixed_page_form_from_post(): array
{
    return [
        'title' => trim((string)($_POST['title'] ?? '')),
        'slug' => trim((string)($_POST['slug'] ?? '')),
        'body' => (string)($_POST['body'] ?? ''),
        'status' => (string)($_POST['status'] ?? 'draft'),
    ];
}

/**
 * @param array{title:string,slug:string,body:string,status:string} $form
 * @return list<string>
 */
function admin_fixed_page_validate(array &$form, int $excludeId = 0): array
{
    $errors = [];

    if ($form['title'] === '') {
        $errors[] = 'タイトルは必須です。';
    }

    $form['slug'] = strtolower($form['slug']);
    if ($form['slug'] === '') {
        $errors[] = 'スラッグは必須です。';
    } elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $form['slug'])) {
        $errors[] = 'スラッグは半角英数字とハイフンのみ使用できます。';
    }

    if ($form['status'] !== 'draft' && $form['status'] !== 'published') {
        $errors[] = '公開状態が不正です。';
    }

    if ($errors !== []) {
        return $errors;
    }

    try {
        $sql = 'SELECT id FROM fixed_pages WHERE slug = :slug';
        $params = [':slug' => $form['slug']];
        if ($excludeId > 0) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $excludeId;
        }
        $sql .= ' LIMIT 1';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch(PDO::FETCH_ASSOC) !== false) {
            $errors[] = 'スラッグが重複しています。別のスラッグを指定してください。';
        }
    } catch (Throwable $exception) {
        error_log('[admin/pages] slug duplicate check failed: ' . $exception->getMessage());
        $errors[] = '固定ページの検証に失敗しました。時間をおいて再度お試しください。';
    }

    return $errors;
}

/**
 * @return array{id:int,title:string,slug:string,body:string,status:string}|null
 */
function admin_fixed_page_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT id, title, slug, body, is_published FROM fixed_pages WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return null;
    }

    return [
        'id' => (int)$row['id'],
        'title' => (string)$row['title'],
        'slug' => (string)$row['slug'],
        'body' => (string)$row['body'],
        'status' => ((int)$row['is_published'] === 1) ? 'published' : 'draft',
    ];
}

/**
 * @param array{title:string,slug:string,body:string,status:string} $form
 */
function admin_fixed_page_create(array $form): void
{
    $stmt = db()->prepare('INSERT INTO fixed_pages (title, slug, body, is_published, created_at, updated_at) VALUES (:title, :slug, :body, :published, NOW(), NOW())');
    $stmt->execute([
        ':title' => $form['title'],
        ':slug' => $form['slug'],
        ':body' => $form['body'],
        ':published' => $form['status'] === 'published' ? 1 : 0,
    ]);
}

/**
 * @param array{title:string,slug:string,body:string,status:string} $form
 */
function admin_fixed_page_update(int $id, array $form): void
{
    $stmt = db()->prepare('UPDATE fixed_pages SET title = :title, slug = :slug, body = :body, is_published = :published, updated_at = NOW() WHERE id = :id');
    $stmt->execute([
        ':id' => $id,
        ':title' => $form['title'],
        ':slug' => $form['slug'],
        ':body' => $form['body'],
        ':published' => $form['status'] === 'published' ? 1 : 0,
    ]);
}

function admin_fixed_page_delete(int $id): bool
{
    $stmt = db()->prepare('DELETE FROM fixed_pages WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
}
