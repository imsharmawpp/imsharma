        </div>
    </main>
</div>
<script>
function confirmDelete(msg) {
    return confirm(msg || 'Are you sure you want to delete this?');
}
function showFlash(msg, type) {
    const div = document.createElement('div');
    div.style.cssText = `position:fixed;top:20px;right:20px;background:${type==='success'?'#10B981':'#EF4444'};color:white;padding:14px 24px;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,0.2);z-index:1000;`;
    div.textContent = msg;
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 4000);
}
</script>
</body>
</html>
