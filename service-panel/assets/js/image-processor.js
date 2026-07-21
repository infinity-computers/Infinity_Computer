/**
 * Image Processor Utility
 * Handles client-side watermarking, timestamping, and compression
 * Supports up to 5 images per submission
 */

const ImageProcessor = {
    MAX_IMAGES: 5,

    process: async (file, watermarkText = "Infinity Computer") => {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = (event) => {
                const img = new Image();
                img.src = event.target.result;
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');

                    // Cap max dimension to 1200px to help reach target file size (100-150KB)
                    const maxDim = 1200;
                    let w = img.width;
                    let h = img.height;
                    if (w > maxDim || h > maxDim) {
                        const ratio = Math.min(maxDim / w, maxDim / h);
                        w = Math.floor(w * ratio);
                        h = Math.floor(h * ratio);
                    }

                    // Set canvas dimensions
                    canvas.width = w;
                    canvas.height = h;

                    // Draw original image (scaled if needed)
                    ctx.drawImage(img, 0, 0, w, h);

                    // Configure Text Styles
                    const baseSize = canvas.width / 30;
                    ctx.shadowColor = "rgba(0, 0, 0, 0.5)";
                    ctx.shadowBlur = 4;
                    ctx.shadowOffsetX = 2;
                    ctx.shadowOffsetY = 2;

                    // 1. Watermark (Opaque Text Only Pattern)
                    ctx.save();
                    const fsize = Math.max(12, canvas.width / 45);
                    ctx.textAlign = "center";
                    ctx.fillStyle = "rgba(255, 255, 255, 0.7)"; // Opaque/Very visible (70% opacity)
                    ctx.font = `600 ${fsize * 0.7}px sans-serif`;

                    // Rotate -45 degrees for upward tilt
                    ctx.rotate(-45 * Math.PI / 180);

                    const stepX = fsize * 7;
                    const stepY = fsize * 4;
                    const range = Math.max(canvas.width, canvas.height) * 2;

                    for (let y = -range; y < range; y += stepY) {
                        const rowIndex = Math.floor(y / stepY);
                        const shift = (rowIndex % 2) * (stepX / 2); // Staggered rows

                        for (let x = -range; x < range; x += stepX) {
                            ctx.fillText("infinity computer", x + shift, y);
                        }
                    }
                    ctx.restore();

                    // 2. Timestamp (Bottom-Right)
                    const timestamp = new Date().toLocaleString('sv-SE').replace('T', ' ');
                    const tsSize = Math.max(20, canvas.width / 30); // Increased Size
                    ctx.font = `bold ${tsSize}px Arial`;
                    ctx.textAlign = "right";

                    const tx = canvas.width - 30;
                    const ty = canvas.height - 30;

                    // Stroke for timestamp
                    ctx.strokeStyle = "rgba(0, 0, 0, 0.9)";
                    ctx.lineWidth = Math.max(3, tsSize / 10);
                    ctx.strokeText(timestamp, tx, ty);

                    // Fill for timestamp
                    ctx.fillStyle = "white";
                    ctx.fillText(timestamp, tx, ty);

                    // Convert to Blob with compression to target 100-150KB
                    const targetMaxKB = 150;
                    let quality = 0.85;
                    const minQuality = 0.1;
                    const qualityStep = 0.05;

                    const tryCompress = (q) => {
                        canvas.toBlob((blob) => {
                            const sizeKB = blob.size / 1024;
                            if (sizeKB <= targetMaxKB || q <= minQuality) {
                                console.log(`Image compressed: ${sizeKB.toFixed(1)}KB at quality ${q.toFixed(2)}`);
                                resolve(blob);
                            } else {
                                tryCompress(Math.max(q - qualityStep, minQuality));
                            }
                        }, 'image/jpeg', q);
                    };

                    tryCompress(quality);
                };
                img.onerror = reject;
            };
            reader.onerror = reject;
        });
    },

    /**
     * Sets up a multi-image upload area.
     * @param {string} addBtnSelector - selector for the "add image" trigger button/label
     * @param {string} previewContainerSelector - selector for the preview grid container
     * @param {boolean} showPreview - whether to show image previews (true) or just a success badge (false)
     */
    setupMultiPreview: (addBtnSelector, previewContainerSelector, showPreview = true) => {
        const container = document.querySelector(previewContainerSelector);
        if (!container) return;

        // Ensure global blobs array is initialized
        if (!Array.isArray(window.lastProcessedBlobs)) {
            window.lastProcessedBlobs = [];
        }

        // Render the current state of the blobs array
        const render = () => {
            container.innerHTML = '';
            if (window.lastProcessedBlobs.length === 0) return;

            const grid = document.createElement('div');
            grid.style.cssText = 'display:grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap:10px; margin-top:12px;';

            window.lastProcessedBlobs.forEach((blob, index) => {
                const sizeKB = (blob.size / 1024).toFixed(1);
                const url = URL.createObjectURL(blob);

                const item = document.createElement('div');
                item.style.cssText = 'position:relative; border:2px solid var(--primary, #1f5fae); border-radius:8px; overflow:hidden; aspect-ratio:1; background:#f1f5f9;';

                if (showPreview) {
                    item.innerHTML = `
                        <img src="${url}" style="width:100%; height:100%; object-fit:cover;" alt="Device image ${index + 1}">
                        <div style="position:absolute; bottom:0; left:0; right:0; background:rgba(0,0,0,0.55); color:#fff; font-size:0.65rem; text-align:center; padding:2px 4px;">${sizeKB} KB</div>
                        <button type="button" data-index="${index}" style="position:absolute; top:4px; right:4px; background:rgba(220,38,38,0.85); color:#fff; border:none; border-radius:50%; width:22px; height:22px; font-size:14px; line-height:1; cursor:pointer; display:flex; align-items:center; justify-content:center;" title="Remove">✕</button>
                    `;
                } else {
                    item.innerHTML = `
                        <div style="width:100%; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:4px; padding:8px; box-sizing:border-box;">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            <span style="font-size:0.65rem; color:#15803d; font-weight:600; text-align:center;">Photo ${index+1}<br>${sizeKB} KB</span>
                        </div>
                        <button type="button" data-index="${index}" style="position:absolute; top:4px; right:4px; background:rgba(220,38,38,0.85); color:#fff; border:none; border-radius:50%; width:22px; height:22px; font-size:14px; line-height:1; cursor:pointer; display:flex; align-items:center; justify-content:center;" title="Remove">✕</button>
                    `;
                }

                // Remove button
                item.querySelector('button').addEventListener('click', (e) => {
                    e.stopPropagation();
                    window.lastProcessedBlobs.splice(index, 1);
                    render();
                });

                grid.appendChild(item);
            });

            container.appendChild(grid);

            // Summary label
            const summary = document.createElement('p');
            summary.style.cssText = 'font-size:0.78rem; color:var(--primary, #1f5fae); font-weight:600; margin-top:6px; margin-bottom:0;';
            summary.textContent = `${window.lastProcessedBlobs.length} / ${ImageProcessor.MAX_IMAGES} image${window.lastProcessedBlobs.length !== 1 ? 's' : ''} added`;
            container.appendChild(summary);
        };

        // Handle file input change for gallery/camera
        const handleFiles = async (files, isCamera = false) => {
            const remaining = ImageProcessor.MAX_IMAGES - window.lastProcessedBlobs.length;
            if (remaining <= 0) {
                alert(`You can only add up to ${ImageProcessor.MAX_IMAGES} images.`);
                return;
            }

            const toProcess = Array.from(files).slice(0, remaining);

            // Show processing state
            const processingMsg = document.createElement('p');
            processingMsg.style.cssText = 'color:var(--primary, #1f5fae); font-size:0.85rem; margin-top:8px;';
            processingMsg.textContent = `Processing ${toProcess.length} image${toProcess.length > 1 ? 's' : ''}...`;
            container.appendChild(processingMsg);

            for (const file of toProcess) {
                try {
                    const blob = await ImageProcessor.process(file);
                    window.lastProcessedBlobs.push(blob);
                } catch (err) {
                    console.error('Failed to process image:', err);
                }
            }

            render();
        };

        // Attach events to all triggers (both gallery and camera labels)
        const triggers = document.querySelectorAll(addBtnSelector);
        triggers.forEach(trigger => {
            const fileInput = trigger.querySelector('input[type="file"]');
            if (!fileInput) return;

            const isCameraInput = fileInput.hasAttribute('capture');

            // Desktop camera modal for camera button
            if (isCameraInput) {
                trigger.addEventListener('click', (e) => {
                    const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
                    if (!isMobile) {
                        e.preventDefault();
                        ImageProcessor.openCameraModal(async (blob) => {
                            const remaining = ImageProcessor.MAX_IMAGES - window.lastProcessedBlobs.length;
                            if (remaining <= 0) {
                                alert(`You can only add up to ${ImageProcessor.MAX_IMAGES} images.`);
                                return;
                            }
                            try {
                                const processedBlob = await ImageProcessor.process(blob);
                                window.lastProcessedBlobs.push(processedBlob);
                                render();
                            } catch (err) {
                                console.error('Failed to process captured image:', err);
                            }
                        });
                    }
                });
            }

            fileInput.addEventListener('change', async (e) => {
                if (e.target.files.length === 0) return;
                await handleFiles(e.target.files);
                // Reset input so same file can be re-selected
                e.target.value = '';
            });
        });
    },

    /**
     * Legacy single-image setup for backwards compatibility
     */
    setupPreview: (inputSelector, previewContainerSelector, showPreview = true) => {
        const inputs = document.querySelectorAll(inputSelector);
        const container = document.querySelector(previewContainerSelector);
        if (inputs.length === 0 || !container) return;

        inputs.forEach(input => {
            const isCameraInput = input.hasAttribute('capture');
            const label = input.parentElement;

            if (isCameraInput) {
                label.addEventListener('click', (e) => {
                    const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
                    if (!isMobile) {
                        e.preventDefault();
                        ImageProcessor.openCameraModal(async (blob) => {
                            container.innerHTML = '<p style="color:var(--primary)">Processing captured image...</p>';
                            try {
                                const processedBlob = await ImageProcessor.process(blob);
                                window.lastProcessedBlob = processedBlob;
                                ImageProcessor.displayFeedback(container, processedBlob, showPreview);
                            } catch (err) {
                                container.innerHTML = '<p style="color:red">Failed to process image.</p>';
                            }
                        });
                    }
                });
            }

            input.addEventListener('change', async (e) => {
                const file = e.target.files[0];
                if (!file) return;

                inputs.forEach(other => { if (other !== input) other.value = ''; });

                container.innerHTML = '<p style="color:var(--primary)">Processing image...</p>';
                try {
                    const processedBlob = await ImageProcessor.process(file);
                    window.lastProcessedBlob = processedBlob;
                    ImageProcessor.displayFeedback(container, processedBlob, showPreview);
                } catch (err) {
                    container.innerHTML = '<p style="color:red">Failed to process image.</p>';
                }
            });
        });
    },

    displayFeedback: (container, blob, showPreview) => {
        const sizeKB = (blob.size / 1024).toFixed(1);
        if (showPreview) {
            const url = URL.createObjectURL(blob);
            container.innerHTML = `
                <div style="margin-top:15px; border:2px dashed var(--primary); padding:10px; border-radius:10px;">
                    <p style="font-size:0.8rem; font-weight:600; color:var(--primary); margin-bottom:10px; text-align: center;">PREVIEW (Watermark & Timestamp Applied) — ${sizeKB} KB</p>
                    <div style="display: flex; justify-content: center;">
                        <img src="${url}" style="width:200px; height:200px; border-radius:8px; box-shadow:0 4px 10px rgba(0,0,0,0.1); object-fit: cover; border: 1px solid #e2e8f0;">
                    </div>
                </div>
            `;
        } else {
            container.innerHTML = `
                <div style="margin-top:15px; border:2px solid #28a745; padding:15px; border-radius:10px; background-color: #d4edda; color: #155724; display: flex; align-items: center; gap: 10px; justify-content: center;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <span style="font-weight: 600;">Image successfully captured and processed! (${sizeKB} KB)</span>
                </div>
            `;
        }
    },

    openCameraModal: async (onCapture) => {
        const modal = document.createElement('div');
        modal.style = "position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:10000; display:flex; align-items:center; justify-content:center; padding:15px; font-family:sans-serif;";
        modal.innerHTML = `
            <div style="background:white; padding:25px; border-radius:20px; max-width:500px; width:100%; position:relative; box-shadow:0 20px 50px rgba(0,0,0,0.3);">
                <button id="closeCam" style="position:absolute; top:15px; right:15px; border:0; background:#eee; width:30px; height:30px; border-radius:50%; cursor:pointer; font-weight:bold;">&times;</button>
                <h3 style="margin-top:0; margin-bottom:15px; color:#1f2a37; text-align:center;">Desktop Camera</h3>
                <video id="camVideo" autoplay playsinline style="width:100%; border-radius:12px; background:#000; aspect-ratio: 4/3; object-fit: cover;"></video>
                <div style="display:flex; gap:12px; margin-top:20px;">
                    <button id="captureBtn" style="flex:2; background:#1f5fae; color:white; border:0; padding:12px; border-radius:10px; font-weight:600; cursor:pointer;">Capture Photo</button>
                    <button id="cancelCam" style="flex:1; background:#f3f4f6; color:#4b5563; border:0; padding:12px; border-radius:10px; font-weight:600; cursor:pointer;">Cancel</button>
                </div>
                <canvas id="camCanvas" style="display:none;"></canvas>
            </div>
        `;
        document.body.appendChild(modal);

        const video = modal.querySelector('#camVideo');
        const canvas = modal.querySelector('#camCanvas');
        let stream = null;

        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { width: { ideal: 1280 }, height: { ideal: 720 } } });
            video.srcObject = stream;
        } catch (err) {
            alert("Unable to access camera. Please check permissions or ensure no other app is using it.");
            document.body.removeChild(modal);
            return;
        }

        modal.querySelector('#captureBtn').onclick = () => {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            canvas.toBlob(blob => {
                onCapture(blob);
                if (stream) stream.getTracks().forEach(t => t.stop());
                document.body.removeChild(modal);
            }, 'image/jpeg', 0.9);
        };

        const close = () => {
            if (stream) stream.getTracks().forEach(t => t.stop());
            document.body.removeChild(modal);
        };
        modal.querySelector('#closeCam').onclick = close;
        modal.querySelector('#cancelCam').onclick = close;
    },

    /**
     * Optional check if any camera is available
     */
    initCameraVisibility: async (selector = '.camera-btn') => {
        const btns = document.querySelectorAll(selector);
        if (btns.length === 0) return;

        const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);

        // On Desktop, we only check if navigator.mediaDevices exists
        if (!isMobile && !navigator.mediaDevices) {
            btns.forEach(b => b.style.display = 'none');
        }
    }
};
