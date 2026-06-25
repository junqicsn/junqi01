<?php
session_start();
define('DEBUG_MODE', false);
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ==================== 作者配置 ====================
define('AUTHOR_USERNAME', 'mxsjh520');   // 作者用户名，密码 mx91

// ==================== 管理员存储 ====================
define('ADMIN_LIST_FILE', __DIR__ . '/.admin_list.json');

function loadAdminList() {
    if (!file_exists(ADMIN_LIST_FILE)) return [];
    $content = file_get_contents(ADMIN_LIST_FILE);
    return $content ? json_decode($content, true) : [];
}

function saveAdminList($admins) {
    $result = file_put_contents(ADMIN_LIST_FILE, json_encode($admins, JSON_PRETTY_PRINT));
    if ($result !== false) chmod(ADMIN_LIST_FILE, 0644);
    return $result !== false;
}

function initAdminList() {
    $admins = loadAdminList();
    $changed = false;
    $authorHash = password_hash('mx91', PASSWORD_DEFAULT);
    $authorExists = false;
    foreach ($admins as &$admin) {
        if ($admin['username'] === AUTHOR_USERNAME) {
            $authorExists = true;
            if (!password_verify('mx91', $admin['hash'])) {
                $admin['hash'] = $authorHash;
                $changed = true;
            }
            break;
        }
    }
    if (!$authorExists) {
        $admins[] = ['username' => AUTHOR_USERNAME, 'hash' => $authorHash];
        $changed = true;
    }
    $adminExists = false;
    foreach ($admins as $admin) {
        if ($admin['username'] === 'admin') {
            $adminExists = true;
            break;
        }
    }
    if (!$adminExists) {
        $admins[] = ['username' => 'admin', 'hash' => password_hash('123', PASSWORD_DEFAULT)];
        $changed = true;
    }
    if ($changed) return saveAdminList($admins);
    return true;
}

function verifyAdmin($username, $password) {
    foreach (loadAdminList() as $admin) {
        if ($admin['username'] === $username && password_verify($password, $admin['hash'])) {
            return true;
        }
    }
    return false;
}

function getAdminHash($username) {
    foreach (loadAdminList() as $admin) {
        if ($admin['username'] === $username) return $admin['hash'];
    }
    return false;
}

function verifyCurrentPassword($username, $password) {
    $hash = getAdminHash($username);
    return $hash && password_verify($password, $hash);
}

// 作者：添加管理员
function authorAddAdmin($authorUsername, $authorPassword, $newUsername, $newPassword) {
    if ($authorUsername !== AUTHOR_USERNAME) return ['success' => false, 'message' => '权限不足'];
    if (!verifyCurrentPassword($authorUsername, $authorPassword)) return ['success' => false, 'message' => '当前密码错误'];
    $newUsername = trim($newUsername);
    if (empty($newUsername)) return ['success' => false, 'message' => '用户名不能为空'];
    if (strlen($newPassword) < 3) return ['success' => false, 'message' => '密码至少3位'];
    $admins = loadAdminList();
    foreach ($admins as $admin) {
        if ($admin['username'] === $newUsername) return ['success' => false, 'message' => '用户名已存在'];
    }
    $admins[] = ['username' => $newUsername, 'hash' => password_hash($newPassword, PASSWORD_DEFAULT)];
    return saveAdminList($admins) ? ['success' => true, 'message' => "管理员 {$newUsername} 添加成功"] : ['success' => false, 'message' => '保存失败'];
}

// 作者：修改任意管理员（不能修改自己）
function authorModifyAdmin($authorUsername, $authorPassword, $targetUsername, $newUsername, $newPassword) {
    if ($authorUsername !== AUTHOR_USERNAME) return ['success' => false, 'message' => '权限不足'];
    if (!verifyCurrentPassword($authorUsername, $authorPassword)) return ['success' => false, 'message' => '当前密码错误'];
    if ($targetUsername === AUTHOR_USERNAME) return ['success' => false, 'message' => '不能修改作者自己'];
    $admins = loadAdminList();
    $index = -1;
    foreach ($admins as $i => $admin) {
        if ($admin['username'] === $targetUsername) { $index = $i; break; }
    }
    if ($index === -1) return ['success' => false, 'message' => '目标管理员不存在'];
    $finalUsername = $admins[$index]['username'];
    $finalHash = $admins[$index]['hash'];
    if (!empty(trim($newUsername))) {
        $nu = trim($newUsername);
        if ($nu === AUTHOR_USERNAME) return ['success' => false, 'message' => '不能修改为作者用户名'];
        foreach ($admins as $j => $a) {
            if ($j !== $index && $a['username'] === $nu) return ['success' => false, 'message' => '新用户名已被占用'];
        }
        $finalUsername = $nu;
    }
    if (!empty($newPassword)) {
        if (strlen($newPassword) < 3) return ['success' => false, 'message' => '密码至少3位'];
        $finalHash = password_hash($newPassword, PASSWORD_DEFAULT);
    }
    if ($finalUsername === $admins[$index]['username'] && $finalHash === $admins[$index]['hash']) {
        return ['success' => false, 'message' => '未做任何修改'];
    }
    $admins[$index]['username'] = $finalUsername;
    $admins[$index]['hash'] = $finalHash;
    return saveAdminList($admins) ? ['success' => true, 'message' => "管理员信息已更新"] : ['success' => false, 'message' => '保存失败'];
}

// 作者：删除管理员（不能删除自己）
function authorDeleteAdmin($authorUsername, $authorPassword, $targetUsername) {
    if ($authorUsername !== AUTHOR_USERNAME) return ['success' => false, 'message' => '权限不足'];
    if (!verifyCurrentPassword($authorUsername, $authorPassword)) return ['success' => false, 'message' => '当前密码错误'];
    if ($targetUsername === AUTHOR_USERNAME) return ['success' => false, 'message' => '不能删除作者自己'];
    $admins = loadAdminList();
    $newAdmins = [];
    $found = false;
    foreach ($admins as $admin) {
        if ($admin['username'] !== $targetUsername) $newAdmins[] = $admin;
        else $found = true;
    }
    if (!$found) return ['success' => false, 'message' => '管理员不存在'];
    if (count($newAdmins) < 1) return ['success' => false, 'message' => '至少需要保留一个管理员'];
    return saveAdminList($newAdmins) ? ['success' => true, 'message' => "管理员 {$targetUsername} 已删除"] : ['success' => false, 'message' => '删除失败'];
}

// 普通管理员：修改自己的账户
function modifySelf($username, $currentPassword, $newUsername, $newPassword) {
    if (!verifyCurrentPassword($username, $currentPassword)) return ['success' => false, 'message' => '当前密码错误'];
    $admins = loadAdminList();
    $index = -1;
    foreach ($admins as $i => $admin) {
        if ($admin['username'] === $username) { $index = $i; break; }
    }
    if ($index === -1) return ['success' => false, 'message' => '账户不存在'];
    $finalUsername = $admins[$index]['username'];
    $finalHash = $admins[$index]['hash'];
    if (!empty(trim($newUsername))) {
        $nu = trim($newUsername);
        if ($nu === AUTHOR_USERNAME && $username !== AUTHOR_USERNAME) return ['success' => false, 'message' => '不能修改为作者用户名'];
        foreach ($admins as $j => $a) {
            if ($j !== $index && $a['username'] === $nu) return ['success' => false, 'message' => '用户名已被占用'];
        }
        $finalUsername = $nu;
    }
    if (!empty($newPassword)) {
        if (strlen($newPassword) < 3) return ['success' => false, 'message' => '密码至少3位'];
        $finalHash = password_hash($newPassword, PASSWORD_DEFAULT);
    }
    if ($finalUsername === $admins[$index]['username'] && $finalHash === $admins[$index]['hash']) {
        return ['success' => false, 'message' => '未做任何修改'];
    }
    $admins[$index]['username'] = $finalUsername;
    $admins[$index]['hash'] = $finalHash;
    $success = saveAdminList($admins);
    if ($success && $finalUsername !== $username) {
        session_destroy();
        return ['success' => true, 'message' => '账户信息已更新，请重新登录', 'logout' => true];
    }
    return $success ? ['success' => true, 'message' => '账户信息已更新'] : ['success' => false, 'message' => '保存失败'];
}

// 初始化
initAdminList();

// 登录处理
if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (verifyAdmin($username, $password)) {
        $_SESSION['loggedin'] = true;
        $_SESSION['admin_username'] = $username;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $loginError = "用户名或密码错误";
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$isLoggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$currentUser = $isLoggedIn ? $_SESSION['admin_username'] : '';
$isAuthor = $isLoggedIn && $currentUser === AUTHOR_USERNAME;

$msg = '';
$err = '';

if ($isLoggedIn) {
    // 作者操作
    if ($isAuthor) {
        if (isset($_POST['author_add'])) {
            $res = authorAddAdmin($currentUser, $_POST['author_current_pass'], $_POST['new_username'], $_POST['new_password']);
            if ($res['success']) $msg = $res['message']; else $err = $res['message'];
        }
        if (isset($_POST['author_modify'])) {
            $res = authorModifyAdmin($currentUser, $_POST['author_current_pass'], $_POST['target_username'], $_POST['modify_new_username'], $_POST['modify_new_password']);
            if ($res['success']) $msg = $res['message']; else $err = $res['message'];
        }
        if (isset($_POST['author_delete'])) {
            $res = authorDeleteAdmin($currentUser, $_POST['author_current_pass'], $_POST['target_username']);
            if ($res['success']) $msg = $res['message']; else $err = $res['message'];
        }
    } else {
        // 普通管理员修改自己
        if (isset($_POST['self_modify'])) {
            $res = modifySelf($currentUser, $_POST['self_current_pass'], $_POST['self_new_username'], $_POST['self_new_password']);
            if ($res['success']) {
                $msg = $res['message'];
                if (isset($res['logout']) && $res['logout']) {
                    session_destroy();
                    header("Location: " . $_SERVER['PHP_SELF'] . "?msg=updated");
                    exit;
                }
            } else {
                $err = $res['message'];
            }
        }
    }
}

// 文件操作
function isSafePath($path) {
    $real = realpath($path);
    return $real !== false && strpos($real, realpath(__DIR__)) === 0;
}
function canDeleteFiles() {
    return isset($_SESSION['admin_username']) && $_SESSION['admin_username'] === AUTHOR_USERNAME;
}
if ($isLoggedIn) {
    if (isset($_GET['download'])) {
        $file = urldecode($_GET['download']);
        if (file_exists($file) && isSafePath($file)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($file).'"');
            readfile($file);
            exit;
        }
    }
    if (isset($_GET['view'])) {
        $file = urldecode($_GET['view']);
        if (file_exists($file) && isSafePath($file)) {
            echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>查看文件</title><style>body{font-family:system-ui;margin:20px;}pre{background:#f5f5f7;padding:20px;border-radius:16px;}</style></head><body><a href='".$_SERVER['PHP_SELF']."'>← 返回</a><pre>".htmlspecialchars(file_get_contents($file))."</pre></body></html>";
            exit;
        }
    }
    if (isset($_GET['delete']) && canDeleteFiles()) {
        $file = urldecode($_GET['delete']);
        if (file_exists($file) && isSafePath($file)) { unlink($file); header("Location: " . $_SERVER['PHP_SELF']); exit; }
    }
}

$files = glob(__DIR__ . '/*.txt');
$files = $files ? array_map('realpath', $files) : [];
$adminList = $isLoggedIn ? loadAdminList() : [];
?>
<?php if (!$isLoggedIn): ?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>黑客终端 · 登录</title><style>
*{margin:0;padding:0;box-sizing:border-box}body{background:#000;font-family:monospace;overflow:hidden;height:100vh;color:#0f0}
#matrixCanvas{position:fixed;top:0;left:0;width:100%;height:100%;z-index:0}
.login-container{position:relative;z-index:10;display:flex;justify-content:center;align-items:center;height:100vh;background:rgba(0,0,0,0.75)}
.hacker-card{background:rgba(0,0,0,0.85);backdrop-filter:blur(8px);border:1px solid #0f0;border-radius:24px;padding:48px 40px;width:450px;max-width:90%;box-shadow:0 0 30px rgba(0,255,0,0.2);text-align:center}
.hacker-card h2{font-size:2rem;letter-spacing:4px;margin-bottom:12px;text-shadow:0 0 8px #0f0}
.input-hacker{width:100%;background:#111;border:1px solid #0f0;border-radius:40px;padding:14px 20px;font-size:1rem;color:#0f0;font-family:monospace;outline:none;margin-bottom:18px}
.input-hacker:focus{box-shadow:0 0 12px #0f0}
.btn-hacker{width:100%;background:#0f0;color:#000;border:none;border-radius:40px;padding:12px;font-weight:bold;font-size:1.1rem;cursor:pointer}
.btn-hacker:hover{background:#8f8;box-shadow:0 0 15px #0f0}
.msg-error{background:rgba(255,0,0,0.2);border-left:4px solid #f00;color:#f88;padding:12px;border-radius:12px;margin-bottom:20px}
.footer-note{margin-top:30px;font-size:0.7rem;color:#3a3}
</style></head>
<body>
<canvas id="matrixCanvas"></canvas>
<div class="login-container"><div class="hacker-card">
<h2>>_ DATA_CRYPT</h2><p>⚡ 管理员验证入口 ⚡</p>
<?php if (isset($loginError)): ?><div class="msg-error">❌ <?php echo htmlspecialchars($loginError); ?></div><?php endif; ?>
<?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?><div class="msg-error" style="border-left-color:#0f0;color:#8f8;">✅ 账户已更新，请重新登录</div><?php endif; ?>
<form method="POST"><input type="text" name="username" class="input-hacker" placeholder="用户名" autofocus required><input type="password" name="password" class="input-hacker" placeholder="密码" required><button type="submit" name="login" class="btn-hacker">[ 登 录 ]</button></form>
<div class="footer-note">[ 作者: mxsjh520 / mx91 | 管理员: admin/123 ]</div>
</div></div>
<script>
const canvas=document.getElementById('matrixCanvas'),ctx=canvas.getContext('2d');
let w=window.innerWidth,h=window.innerHeight;
canvas.width=w;canvas.height=h;
const chars="01ABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()_+{}[];:<>?,.\\|`~".split('');
const fontSize=18,cols=Math.floor(w/fontSize);
let drops=Array(cols).fill(0).map(()=>Math.floor(Math.random()*-h/fontSize));
function draw(){ctx.fillStyle='rgba(0,0,0,0.05)';ctx.fillRect(0,0,w,h);ctx.fillStyle='#0f0';ctx.font=fontSize+'px monospace';
for(let i=0;i<drops.length;i++){ctx.fillText(chars[Math.floor(Math.random()*chars.length)],i*fontSize,drops[i]*fontSize);if(drops[i]*fontSize>h&&Math.random()>0.975)drops[i]=0;drops[i]++;}}
setInterval(draw,50);
window.addEventListener('resize',()=>{w=window.innerWidth;h=window.innerHeight;canvas.width=w;canvas.height=h;const nc=Math.floor(w/fontSize);drops.length=nc;for(let i=0;i<nc;i++)if(drops[i]===undefined)drops[i]=Math.floor(Math.random()*-h/fontSize);});
</script>
</body></html>
<?php exit; endif; ?>

<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>🍎 潮流数据号 | 纯白管理系统</title><style>
*{margin:0;padding:0;box-sizing:border-box}body{background:#fff;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#1d1d1f;padding:0 0 40px}
.glass-card{background:#fff;border-radius:32px;box-shadow:0 8px 30px rgba(0,0,0,0.05);border:1px solid #e9e9ef}
.container{max-width:1400px;margin:0 auto;padding:24px 28px}
.marquee-wrapper{background:#f5f5f7;border-radius:60px;padding:12px 20px;margin-bottom:32px;overflow:hidden;white-space:nowrap}
.marquee-content{display:inline-block;animation:scroll 18s linear infinite;font-weight:500}
.marquee-content span{margin:0 30px}
@keyframes scroll{0%{transform:translateX(0)}100%{transform:translateX(-50%)}}
.apple-dynamic{position:fixed;bottom:30px;right:30px;width:60px;height:60px;background:radial-gradient(circle,#ffb347,#ff6a00);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2.4rem;box-shadow:0 10px 25px rgba(255,106,0,0.3);cursor:pointer;z-index:999;animation:float 3s infinite ease-in-out}
@keyframes float{0%{transform:translateY(0)}50%{transform:translateY(-12px) rotate(5deg)}100%{transform:translateY(0)}}
.header-bar{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;margin-bottom:28px;padding-bottom:12px;border-bottom:1px solid #f0f0f2}
.logo-area h1{font-size:1.9rem;font-weight:600;background:linear-gradient(135deg,#000,#434343);-webkit-background-clip:text;background-clip:text;color:transparent}
.logout-btn{background:#000;color:#fff;padding:8px 20px;border-radius:40px;text-decoration:none}
.input-modern{border:1px solid #e4e4e7;border-radius:48px;padding:12px 20px;font-size:1rem;width:100%;outline:none}
.input-modern:focus{border-color:#000;box-shadow:0 0 0 3px rgba(0,0,0,0.05)}
.btn-white{background:#000;color:#fff;border:none;border-radius:48px;padding:10px 24px;font-weight:500;cursor:pointer}
.data-table{width:100%;border-collapse:separate;border-spacing:0;background:#fff;border-radius:28px;overflow:hidden;margin:20px 0}
.data-table th{background:#f9f9fb;padding:18px 16px;font-weight:600;text-align:left;border-bottom:1px solid #ececf0}
.data-table td{padding:16px;border-bottom:1px solid #f0f0f2}
.action-buttons a{display:inline-block;margin:0 6px;padding:6px 16px;border-radius:30px;font-size:0.8rem;text-decoration:none;font-weight:500}
.download-btn{background:#e8e8ed;color:#000}
.view-btn{background:#e8e8ed;color:#000}
.delete-btn{background:#ffe8e8;color:#d70015}
.settings-card{margin-top:40px;padding:24px 28px;border-radius:36px}
.flex-row{display:flex;gap:20px;flex-wrap:wrap;align-items:flex-end}
.form-group{flex:1;min-width:180px}
.msg-success{background:#e6f7e6;color:#1e7e1e;padding:12px 20px;border-radius:50px;margin-bottom:20px}
.msg-error{background:#ffe6e6;color:#c00;padding:12px 20px;border-radius:50px;margin-bottom:20px}
.live-clock{font-size:1rem;background:#f5f5f7;padding:8px 18px;border-radius:50px;display:inline-block}
.stats{color:#6c6c70;font-size:0.85rem;margin-top:10px}
.admin-table{width:100%;border-collapse:collapse;margin-top:15px}
.admin-table th,.admin-table td{padding:12px 8px;border-bottom:1px solid #eee;text-align:left}
.small-btn{background:#e8e8ed;border:none;border-radius:30px;padding:6px 12px;cursor:pointer;font-size:0.75rem;margin-right:8px}
.delete-btn-admin{background:#ffe8e8;color:#d70015}
hr{margin:20px 0}
@media(max-width:760px){.container{padding:16px}.data-table th,.data-table td{padding:12px 8px;font-size:13px}.action-buttons a{padding:4px 10px}.flex-row{flex-direction:column;gap:12px}}
</style></head>
<body>
<div class="container">
<div class="marquee-wrapper"><div class="marquee-content"><span>⚡ 潮流数据号 · 动态管理系统 ⚡</span><span>🍎 纯白极简 · 跑马灯特效 🍎</span><span>🔮 实时文件管理 · 苹果灵动岛风格 🔮</span><span>✨ 作者可管理所有管理员 ✨</span><span>⚡ 潮流数据号 · 动态管理系统 ⚡</span><span>🍎 纯白极简 · 跑马灯特效 🍎</span></div></div>

<div class="header-bar"><div class="logo-area"><h1>📁 数据号 · 潮流舱</h1><div class="stats">✅ 管理员：<?php echo htmlspecialchars($currentUser); ?> <?php if($isAuthor): ?><span style="background:#000;color:#fff;padding:2px 10px;border-radius:20px;margin-left:10px;">✍️ 作者权限</span><?php endif; ?> | 文件总数: <?php echo count($files); ?> 个TXT</div></div><div style="display:flex;gap:14px;align-items:center;"><div class="live-clock" id="liveClock"></div><a href="?logout=1" class="logout-btn">退出登录</a></div></div>

<?php if($err): ?><div class="msg-error">❌ <?php echo htmlspecialchars($err); ?></div><?php endif; ?>
<?php if($msg): ?><div class="msg-success">✅ <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<!-- TXT 文件列表 -->
<div class="glass-card" style="padding:8px 0;overflow-x:auto"><table class="data-table"><thead><tr><th>📄 文件名</th><th>📦 大小</th><th>🕒 修改时间</th><th>⚡ 操作</th></tr></thead><tbody>
<?php if(!empty($files)): foreach($files as $file): $fs=filesize($file); $ft=filemtime($file); ?>
<tr><td><?php echo htmlspecialchars(basename($file)); ?></td><td><?php echo number_format($fs/1024,2); ?> KB</td><td><?php echo date('Y-m-d H:i:s',$ft); ?></td><td class="action-buttons"><a href="?download=<?php echo urlencode($file); ?>" class="download-btn">⬇️ 下载</a><a href="?view=<?php echo urlencode($file); ?>" class="view-btn">👁️ 查看</a><?php if(canDeleteFiles()): ?><a href="?delete=<?php echo urlencode($file); ?>" class="delete-btn" onclick="return confirm('确定删除？')">🗑️ 删除</a><?php endif; ?></td></tr>
<?php endforeach; else: ?><tr><td colspan="4" style="text-align:center;padding:48px">✨ 当前目录下暂无 .txt 文件 ✨</td></tr><?php endif; ?>
</tbody></table></div>

<!-- 管理员管理区域 -->
<div class="settings-card glass-card">
<?php if($isAuthor): ?>
    <h3>👥 作者管理 · 所有管理员列表 (共 <?php echo count($adminList); ?> 人)</h3>
    <div class="stats">⚠️ 作者可以添加、修改、删除任意管理员（不能删除自己）。任何修改都需要验证作者密码。</div>
    <hr>
    <!-- 添加管理员表单 -->
    <h4>➕ 添加新管理员</h4>
    <form method="POST">
        <div class="flex-row" style="margin-bottom:20px">
            <div class="form-group"><label>🔐 作者密码验证</label><input type="password" name="author_current_pass" class="input-modern" required></div>
            <div class="form-group"><label>新用户名</label><input type="text" name="new_username" class="input-modern" required></div>
            <div class="form-group"><label>新密码</label><input type="password" name="new_password" class="input-modern" required></div>
            <div class="form-group"><button type="submit" name="author_add" class="btn-white">➕ 添加</button></div>
        </div>
    </form>
    <hr>
    <!-- 现有管理员列表，带修改/删除 -->
    <h4>✏️ 修改 / ❌ 删除管理员</h4>
    <div style="overflow-x:auto"><table class="admin-table"><thead><tr><th>用户名</th><th>修改</th><th>删除</th></tr></thead><tbody>
    <?php foreach($adminList as $admin): ?>
    <tr>
        <td><?php echo htmlspecialchars($admin['username']); ?></td>
        <td>
            <form method="POST" style="display:inline-block">
                <input type="hidden" name="target_username" value="<?php echo htmlspecialchars($admin['username']); ?>">
                <input type="hidden" name="author_current_pass" value="" id="mod_pass_<?php echo md5($admin['username']); ?>">
                <input type="text" name="modify_new_username" placeholder="新用户名(留空不变)" style="border-radius:30px;border:1px solid #ccc;padding:4px 8px;width:130px;">
                <input type="password" name="modify_new_password" placeholder="新密码(留空不变)" style="border-radius:30px;border:1px solid #ccc;padding:4px 8px;width:130px;">
                <button type="submit" name="author_modify" class="small-btn" onclick="return confirm('修改前请确保上方作者密码已填写正确！');">修改</button>
            </form>
        </td>
        <td>
            <form method="POST" style="display:inline-block" onsubmit="return confirm('确定删除该管理员？');">
                <input type="hidden" name="target_username" value="<?php echo htmlspecialchars($admin['username']); ?>">
                <input type="hidden" name="author_current_pass" value="" id="del_pass_<?php echo md5($admin['username']); ?>">
                <button type="submit" name="author_delete" class="small-btn delete-btn-admin" <?php echo ($admin['username'] === AUTHOR_USERNAME) ? 'disabled title="不能删除作者自己"' : ''; ?>>删除</button>
            </form>
        </td>
    </tr>
    <script>
        (function(u){
            let mainPass = document.querySelector('input[name="author_current_pass"]');
            if(mainPass){
                let modInp = document.getElementById('mod_pass_'+u);
                let delInp = document.getElementById('del_pass_'+u);
                if(modInp){ mainPass.addEventListener('input',function(){ modInp.value=this.value; }); modInp.value=mainPass.value; }
                if(delInp){ mainPass.addEventListener('input',function(){ delInp.value=this.value; }); delInp.value=mainPass.value; }
            }
        })('<?php echo md5($admin['username']); ?>');
    </script>
    <?php endforeach; ?>
    </tbody></table></div>
<?php else: ?>
    <!-- 普通管理员只能修改自己的账户 -->
    <h3>👤 修改我的账户信息</h3>
    <div class="stats">⚠️ 只能修改自己的用户名/密码，不能查看或修改其他管理员。</div>
    <form method="POST">
        <div class="flex-row" style="margin-bottom:20px">
            <div class="form-group"><label>🔐 当前密码验证</label><input type="password" name="self_current_pass" class="input-modern" required></div>
            <div class="form-group"><label>新用户名（留空则不修改）</label><input type="text" name="self_new_username" class="input-modern" placeholder="新用户名"></div>
            <div class="form-group"><label>新密码（留空则不修改）</label><input type="password" name="self_new_password" class="input-modern" placeholder="至少3位"></div>
            <div class="form-group"><button type="submit" name="self_modify" class="btn-white">✏️ 修改我的账户</button></div>
        </div>
    </form>
<?php endif; ?>
<div class="stats">💡 提示：作者拥有全部管理权限；普通管理员只能修改自己的登录信息，无权添加/删除其他管理员。</div>
</div>

<div class="apple-dynamic" id="appleDynamic">🍎</div>
<div style="text-align:center;margin-top:48px;color:#aaa;">© 梦想 · 纯白极速后台</div>
</div>
<script>function updateClock(){var d=new Date();document.getElementById('liveClock')&&(document.getElementById('liveClock').innerText='🕒 '+d.toLocaleTimeString('zh-CN',{hour12:false}));}updateClock();setInterval(updateClock,1000);document.getElementById('appleDynamic')?.addEventListener('click',function(){this.style.transform='scale(1.3)';setTimeout(()=>this.style.transform='',200);});</script>
</body></html>