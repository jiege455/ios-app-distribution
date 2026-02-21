<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// 后台底部模板
if (!defined('ADMIN_LOADED')) {
    die('直接访问不允许');
}
?>
    </main>
    
    <script>
        function toggleSidebar() {
            var sidebar = document.querySelector('.sidebar');
            var overlay = document.querySelector('.sidebar-overlay');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
        }
        
        document.querySelectorAll('.nav-item').forEach(link => {
            link.addEventListener('click', function(e) {
                if (window.innerWidth < 768) {
                    toggleSidebar();
                }
            });
        });
        
        function copyCleanupUrl() {
            var urlElement = document.getElementById('cleanupUrl');
            var url = urlElement.textContent;
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function() {
                    alert('已复制到剪贴板！');
                }).catch(function() {
                    fallbackCopy(url);
                });
            } else {
                fallbackCopy(url);
            }
        }
        
        function fallbackCopy(text) {
            var textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                alert('已复制到剪贴板！');
            } catch (err) {
                alert('复制失败，请手动复制');
            }
            document.body.removeChild(textArea);
        }
        
        function toggleSelectAll(checkbox, className) {
            var checkboxes = document.querySelectorAll('.' + className);
            checkboxes.forEach(function(cb) {
                cb.checked = checkbox.checked;
            });
            updateSelectedCount(className);
        }
        
        function updateSelectedCount(className) {
            var checkboxes = document.querySelectorAll('.' + className + ':checked');
            var count = checkboxes.length;
            var countSpan = document.getElementById('selectedCount');
            var deleteBtn = document.getElementById('batchDeleteBtn');
            
            if (countSpan && deleteBtn) {
                if (count > 0) {
                    countSpan.textContent = '已选中 ' + count + ' 个';
                    deleteBtn.disabled = false;
                } else {
                    countSpan.textContent = '';
                    deleteBtn.disabled = true;
                }
            }
            
            var allCheckboxes = document.querySelectorAll('.' + className);
            var selectAllCheckbox = document.getElementById('selectAll');
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = (count > 0 && count === allCheckboxes.length);
            }
        }
        
        function confirmBatchDelete() {
            var checkboxes = document.querySelectorAll('.app-checkbox:checked');
            var count = checkboxes.length;
            
            if (count === 0) {
                alert('请先选择要删除的应用');
                return false;
            }
            
            return confirm('确定要删除选中的 ' + count + ' 个应用吗？此操作不可恢复！');
        }
    </script>
</body>
</html>
