<?php
/**
 * AIF Chat - 图形验证码模块
 * 
 * 生成简单的数学验证码，输出为 SVG 格式
 */

declare(strict_types=1);

/**
 * 生成验证码
 *
 * @return array{captcha_id: string, svg: string, expires_in: int}
 */
function generateCaptcha(): array
{
    $captchaId = bin2hex(random_bytes(16));

    // 生成随机数学题
    $a = random_int(1, 20);
    $b = random_int(1, 20);
    $ops = ['+', '-'];
    $op = $ops[array_rand($ops)];

    if ($op === '-' && $a < $b) {
        [$a, $b] = [$b, $a]; // 确保结果为正数
    }

    $answer = (string) ($op === '+' ? $a + $b : $a - $b);
    $expression = "$a $op $b = ?";

    // 保存到数据库
    $expiresAt = date('Y-m-d H:i:s', time() + 300);
    $stmt = db()->prepare(
        'INSERT INTO captcha (id, answer, expires_at) VALUES (?, ?, ?)'
    );
    $stmt->execute([$captchaId, $answer, $expiresAt]);

    return [
        'captcha_id' => $captchaId,
        'svg' => renderCaptchaSvg($expression),
        'expires_in' => 300,
    ];
}

/**
 * 验证验证码
 */
function verifyCaptcha(string $captchaId, string $answer): bool
{
    $stmt = db()->prepare(
        'SELECT answer FROM captcha WHERE id = ? AND expires_at > NOW()'
    );
    $stmt->execute([$captchaId]);
    $row = $stmt->fetch();

    if (!$row) {
        return false;
    }

    // 无论成功失败，都删除验证码（一次性使用）
    $stmt = db()->prepare('DELETE FROM captcha WHERE id = ?');
    $stmt->execute([$captchaId]);

    return trim($answer) === trim($row['answer']);
}

/**
 * 清理过期验证码
 */
function cleanExpiredCaptcha(): void
{
    db()->exec('DELETE FROM captcha WHERE expires_at < NOW()');
}

/**
 * 渲染验证码为 SVG
 */
function renderCaptchaSvg(string $text): string
{
    $width = 200;
    $height = 60;
    $lines = [];
    $circles = [];

    // 干扰线
    for ($i = 0; $i < 3; $i++) {
        $x1 = random_int(0, $width);
        $y1 = random_int(0, $height);
        $x2 = random_int(0, $width);
        $y2 = random_int(0, $height);
        $color = sprintf('rgba(%d,%d,%d,0.3)', random_int(0, 200), random_int(0, 200), random_int(0, 200));
        $lines[] = "<line x1=\"$x1\" y1=\"$y1\" x2=\"$x2\" y2=\"$y2\" stroke=\"$color\" stroke-width=\"2\" />";
    }

    // 干扰点
    for ($i = 0; $i < 30; $i++) {
        $cx = random_int(0, $width);
        $cy = random_int(0, $height);
        $r = random_int(1, 3);
        $circles[] = "<circle cx=\"$cx\" cy=\"$cy\" r=\"$r\" fill=\"rgba(100,100,100,0.2)\" />";
    }

    $fontSize = 32;
    $textX = 30;
    $textY = 42;

    $l0 = $lines[0] ?? '';
    $l1 = $lines[1] ?? '';
    $l2 = $lines[2] ?? '';
    $c0 = $circles[0] ?? '';
    $c1 = $circles[1] ?? '';

    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">'
      . '<rect width="' . $width . '" height="' . $height . '" fill="#f5f5f5" rx="8" />'
      . $l0 . $l1 . $l2 . $c0 . $c1
      . '<text x="' . $textX . '" y="' . $textY . '" font-size="' . $fontSize . '" font-family="monospace" font-weight="bold" fill="#333" transform="rotate(-3, 100, 30)">' . htmlspecialchars($text) . '</text>'
      . '</svg>';
}
