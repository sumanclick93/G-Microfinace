
    <!-- Modal Start -->
    <div class="modal fade" id="staticBackdrop" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
        aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog  modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body">
                    <h5 class="modal-title" id="staticBackdropLabel">Logging Out</h5>
                    <p>Are you sure you want to log out?</p>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    <div class="button-box">
                        <button type="button" class="btn btn--no" data-bs-dismiss="modal">No</button>
                        <a href="logout.php" class="btn btn--yes btn-primary">Yes</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal End -->

    <!-- latest js -->
    <script src="assets/js/jquery-3.6.0.min.js"></script>

    <!-- DataTables JS -->
    <script src="assets/js/jquery.dataTables.js"></script>
    <script src="assets/js/custom-data-table.js"></script>

    <!-- Bootstrap js -->
    <script src="assets/js/bootstrap/bootstrap.bundle.min.js"></script>

    <!-- feather icon js -->
    <script src="assets/js/icons/feather-icon/feather.min.js"></script>
    <script src="assets/js/icons/feather-icon/feather-icon.js"></script>

    <!-- scrollbar simplebar js -->
    <script src="assets/js/scrollbar/simplebar.js"></script>
    <script src="assets/js/scrollbar/custom.js"></script>

    <!-- Sidebar jquery -->
    <script src="assets/js/config.js"></script>

    <!-- tooltip init js -->
    <script src="assets/js/tooltip-init.js"></script>

    <!-- Plugins JS -->
    <script src="assets/js/sidebar-menu.js"></script>
    <script src="assets/js/notify/bootstrap-notify.min.js"></script>
    <script src="assets/js/notify/index.js"></script>

    <!-- Apexchar js -->
    <script src="assets/js/chart/apex-chart/apex-chart1.js"></script>
    <script src="assets/js/chart/apex-chart/moment.min.js"></script>
    <script src="assets/js/chart/apex-chart/apex-chart.js"></script>
    <script src="assets/js/chart/apex-chart/stock-prices.js"></script>
    <script src="assets/js/chart/apex-chart/chart-custom1.js"></script>


    <!-- slick slider js -->
    <script src="assets/js/slick.min.js"></script>
    <script src="assets/js/custom-slick.js"></script>

    <!-- customizer js -->
    <script src="assets/js/customizer.js"></script>

    <!-- ratio js -->
    <script src="assets/js/ratio.js"></script>

    <!-- sidebar effect -->
    <script src="assets/js/sidebareffect.js"></script>

    <!-- Theme js -->
    <script src="assets/js/script.js"></script>

    <!-- Lightbox Image Zoom Modal -->
    <div id="globalImageZoomModal" class="img-zoom-modal" tabindex="-1" style="display: none;">
        <div class="img-zoom-backdrop"></div>
        <div class="img-zoom-toolbar">
            <button type="button" class="img-zoom-btn" id="imgZoomInBtn" title="Zoom In (+)"><i class="ri-zoom-in-line"></i></button>
            <button type="button" class="img-zoom-btn" id="imgZoomOutBtn" title="Zoom Out (-)"><i class="ri-zoom-out-line"></i></button>
            <button type="button" class="img-zoom-btn" id="imgZoomResetBtn" title="Reset (1:1)"><i class="ri-refresh-line"></i></button>
            <button type="button" class="img-zoom-btn" id="imgZoomRotateBtn" title="Rotate (90°)"><i class="ri-clockwise-line"></i></button>
            <button type="button" class="img-zoom-btn img-zoom-close" id="imgZoomCloseBtn" title="Close (Esc)"><i class="ri-close-line"></i></button>
        </div>
        <div class="img-zoom-container">
            <img id="globalZoomedImage" src="" alt="Zoomed View">
        </div>
    </div>

    <style>
    .img-zoom-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        z-index: 999999;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .img-zoom-backdrop {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.88);
        backdrop-filter: blur(5px);
    }
    .img-zoom-toolbar {
        position: absolute;
        top: 25px;
        right: 25px;
        z-index: 1000001;
        display: flex;
        gap: 10px;
        background: rgba(20, 20, 20, 0.85);
        padding: 8px 14px;
        border-radius: 30px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.15);
    }
    .img-zoom-btn {
        background: transparent;
        border: none;
        color: #ffffff;
        font-size: 22px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .img-zoom-btn:hover {
        background: rgba(255, 255, 255, 0.25);
        transform: scale(1.1);
    }
    .img-zoom-close:hover {
        background: #e63946 !important;
    }
    .img-zoom-container {
        position: relative;
        z-index: 1000000;
        max-width: 92vw;
        max-height: 92vh;
        display: flex;
        align-items: center;
        justify-content: center;
        user-select: none;
        touch-action: none;
    }
    .img-zoom-container img {
        max-width: 88vw;
        max-height: 88vh;
        object-fit: contain;
        border-radius: 8px;
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.6);
        transition: transform 0.15s ease-out;
        cursor: grab;
    }
    .img-zoom-container img:active {
        cursor: grabbing;
    }
    img:not(.no-zoom):not(.logo-wrapper img):not(.logo-icon-wrapper img),
    a[href$=".jpg"], a[href$=".jpeg"], a[href$=".png"], a[href$=".webp"], a[href$=".gif"],
    a[href*="upload/"], a[href*="uploads/"] {
        cursor: zoom-in;
    }
    </style>

    <script>
    $(document).ready(function() {
        let scale = 1;
        let rotation = 0;
        let isDragging = false;
        let startX = 0, startY = 0, translateX = 0, translateY = 0;

        function updateTransform() {
            $('#globalZoomedImage').css('transform', `translate(${translateX}px, ${translateY}px) scale(${scale}) rotate(${rotation}deg)`);
        }

        function resetZoom() {
            scale = 1;
            rotation = 0;
            translateX = 0;
            translateY = 0;
            updateTransform();
        }

        function openModal(imageSrc) {
            if (!imageSrc || imageSrc === '#' || imageSrc.startsWith('javascript:')) return;
            $('#globalZoomedImage').attr('src', imageSrc);
            resetZoom();
            $('#globalImageZoomModal').fadeIn(200);
            $('body').css('overflow', 'hidden');
        }

        function closeModal() {
            $('#globalImageZoomModal').fadeOut(200, function() {
                $('#globalZoomedImage').attr('src', '');
                resetZoom();
            });
            $('body').css('overflow', '');
        }

        $(document).on('click', 'img, a[href$=".jpg"], a[href$=".jpeg"], a[href$=".png"], a[href$=".webp"], a[href$=".gif"], a[href*="upload/"], a[href*="uploads/"]', function(e) {
            if ($(this).hasClass('no-zoom') || $(this).closest('.logo-wrapper, .logo-icon-wrapper, .no-zoom').length) {
                return;
            }

            let targetSrc = '';
            if (this.tagName.toLowerCase() === 'img') {
                targetSrc = $(this).attr('src');
            } else if (this.tagName.toLowerCase() === 'a') {
                let href = $(this).attr('href');
                if (href && (href.match(/\.(jpeg|jpg|gif|png|webp)(\?.*)?$/i) || href.includes('upload/') || href.includes('uploads/'))) {
                    e.preventDefault();
                    targetSrc = href;
                } else {
                    return;
                }
            }

            if (targetSrc && targetSrc !== '#' && !targetSrc.startsWith('javascript:')) {
                e.preventDefault();
                e.stopPropagation();
                openModal(targetSrc);
            }
        });

        $('#imgZoomCloseBtn, .img-zoom-backdrop').on('click', function() {
            closeModal();
        });

        $('#imgZoomInBtn').on('click', function(e) {
            e.stopPropagation();
            scale = Math.min(scale + 0.3, 6);
            updateTransform();
        });

        $('#imgZoomOutBtn').on('click', function(e) {
            e.stopPropagation();
            scale = Math.max(scale - 0.3, 0.4);
            updateTransform();
        });

        $('#imgZoomResetBtn').on('click', function(e) {
            e.stopPropagation();
            resetZoom();
        });

        $('#imgZoomRotateBtn').on('click', function(e) {
            e.stopPropagation();
            rotation = (rotation + 90) % 360;
            updateTransform();
        });

        $(document).on('keydown', function(e) {
            if ($('#globalImageZoomModal').is(':visible')) {
                if (e.key === 'Escape') closeModal();
                else if (e.key === '+' || e.key === '=') { scale = Math.min(scale + 0.3, 6); updateTransform(); }
                else if (e.key === '-') { scale = Math.max(scale - 0.3, 0.4); updateTransform(); }
                else if (e.key === 'r' || e.key === 'R') { rotation = (rotation + 90) % 360; updateTransform(); }
                else if (e.key === '0') resetZoom();
            }
        });

        $('#globalImageZoomModal').on('wheel', function(e) {
            e.preventDefault();
            if (e.originalEvent.deltaY < 0) {
                scale = Math.min(scale + 0.2, 6);
            } else {
                scale = Math.max(scale - 0.2, 0.4);
            }
            updateTransform();
        });

        const $zoomImg = $('#globalZoomedImage');
        $zoomImg.on('mousedown touchstart', function(e) {
            e.preventDefault();
            isDragging = true;
            let clientX = e.clientX || (e.originalEvent.touches && e.originalEvent.touches[0].clientX);
            let clientY = e.clientY || (e.originalEvent.touches && e.originalEvent.touches[0].clientY);
            startX = clientX - translateX;
            startY = clientY - translateY;
        });

        $(document).on('mousemove touchmove', function(e) {
            if (!isDragging) return;
            let clientX = e.clientX || (e.originalEvent.touches && e.originalEvent.touches[0].clientX);
            let clientY = e.clientY || (e.originalEvent.touches && e.originalEvent.touches[0].clientY);
            translateX = clientX - startX;
            translateY = clientY - startY;
            updateTransform();
        });

        $(document).on('mouseup touchend', function() {
            isDragging = false;
        });
    });
    </script>
