<?php
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>false,'msg'=>'跨站 SSO 已移除：AI/API 站使用独立用户系统。'], JSON_UNESCAPED_UNICODE);
