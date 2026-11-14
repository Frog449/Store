const searchInput = document.querySelector("#product-search");
const cards = [...document.querySelectorAll(".product-card")];
const filters = [...document.querySelectorAll(".filter-chip[data-category]")];
const statusFilters = [...document.querySelectorAll(".status-filter-chip[data-status-filter]")];
const resultCount = document.querySelector("#result-count");
const emptySearch = document.querySelector("#empty-search");
let activeCategory = "all";
let activeStatus = "all";

// Sidebar Drawer (Mobile) & Admin Accordion Handlers
(function() {
    const sidebar = document.querySelector("#site-sidebar");
    const backdrop = document.querySelector("#sidebar-backdrop");
    const mobileToggle = document.querySelector("#mobile-menu-toggle");
    const closeBtn = document.querySelector("#sidebar-close-btn");

    function openSidebar() {
        if (sidebar) sidebar.classList.add("open");
        if (backdrop) backdrop.classList.add("active");
        document.body.style.overflow = "hidden";
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove("open");
        if (backdrop) backdrop.classList.remove("active");
        document.body.style.overflow = "";
    }

    if (mobileToggle) mobileToggle.addEventListener("click", openSidebar);
    if (closeBtn) closeBtn.addEventListener("click", closeSidebar);
    if (backdrop) backdrop.addEventListener("click", closeSidebar);

    // Sidebar Admin Accordion
    const adminAccordion = document.querySelector("#sidebar-admin-accordion");
    const adminToggle = document.querySelector("#sidebar-admin-toggle");
    if (adminAccordion && adminToggle) {
        adminToggle.addEventListener("click", (e) => {
            e.preventDefault();
            const isOpen = adminAccordion.classList.toggle("open");
            adminToggle.setAttribute("aria-expanded", String(isOpen));
        });
    }

    // Close mobile sidebar on Escape key
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && sidebar && sidebar.classList.contains("open")) {
            closeSidebar();
        }
    });

    // Standalone PWA / Mobile App detection: hide app download links
    const isApp = window.matchMedia('(display-mode: standalone)').matches ||
                  window.navigator.standalone === true ||
                  navigator.userAgent.includes('StoreAutomeationApp');
    if (isApp) {
        document.documentElement.classList.add('is-mobile-app');
        document.cookie = 'is_standalone=1; path=/; max-age=31536000; SameSite=Lax';
    }
})();

function filterProducts() {
    const query = searchInput.value.trim().toLocaleLowerCase("th");
    let visibleCount = 0;

    cards.forEach((card) => {
        const matchesQuery = card.dataset.search.includes(query);
        const matchesCategory = activeCategory === "all" || card.dataset.category === activeCategory;
        const matchesStatus = activeStatus === "all" || card.dataset.statusKey === activeStatus;
        const visible = matchesQuery && matchesCategory && matchesStatus;
        card.hidden = !visible;
        visibleCount += Number(visible);
    });

    resultCount.textContent = `แสดง ${visibleCount} รายการ`;
    if (visibleCount === 0) {
        const statusLabelMap = { normal: "ปกติ", low: "ควรสั่งซื้อ", negative: "หมด" };
        if (query !== "") {
            emptySearch.textContent = "ไม่พบสินค้าที่ตรงกับคำค้นหา ลองค้นหาด้วยคำอื่น";
        } else if (activeStatus !== "all") {
            emptySearch.textContent = `ไม่มีสินค้าที่มีสถานะ "${statusLabelMap[activeStatus] || activeStatus}"${activeCategory !== "all" ? ` ในหมวดหมู่ "${activeCategory}"` : ""}`;
        } else if (activeCategory !== "all") {
            emptySearch.textContent = `ยังไม่มีสินค้าในหมวดหมู่ "${activeCategory}"`;
        } else {
            emptySearch.textContent = "ไม่พบสินค้าในระบบ";
        }
    }
    emptySearch.hidden = visibleCount !== 0;
}

if (searchInput) {
    searchInput.addEventListener("input", filterProducts);

    filters.forEach((filter) => {
        filter.addEventListener("click", () => {
            activeCategory = filter.dataset.category;
            if (activeCategory === "all") {
                activeStatus = "all";
                statusFilters.forEach((s) => s.classList.remove("selected"));
            }
            filters.forEach((item) => item.classList.toggle("selected", item === filter));
            filterProducts();
        });
    });

    statusFilters.forEach((statusBtn) => {
        statusBtn.addEventListener("click", () => {
            const targetStatus = statusBtn.dataset.statusFilter;
            if (activeStatus === targetStatus) {
                activeStatus = "all";
                statusBtn.classList.remove("selected");
            } else {
                activeStatus = targetStatus;
                statusFilters.forEach((item) => item.classList.toggle("selected", item === statusBtn));
            }
            filterProducts();
        });
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "/" && !["INPUT", "TEXTAREA"].includes(document.activeElement.tagName)) {
            event.preventDefault();
            searchInput.focus();
        }
    });
}

const productDetailDialog = document.querySelector("#product-detail-dialog");
if (productDetailDialog) {
    const detailTitle = document.querySelector("#product-detail-title");
    const detailCode = document.querySelector("#product-detail-code");
    const editButton = document.querySelector("#product-detail-edit");
    const deleteForm = productDetailDialog.querySelector("[data-product-delete-form]");
    const editDialog = document.querySelector("#product-edit-dialog");
    const detailImage = document.querySelector("#product-detail-image");
    const detailImageEmpty = document.querySelector("#product-detail-image-empty");
    const editForm = editDialog ? editDialog.querySelector("form") : null;
    const editImagePreview = editForm ? editForm.querySelector(".product-image-preview") : null;
    const editImage = editImagePreview ? editImagePreview.querySelector("img") : null;
    const editImageEmpty = editImagePreview ? editImagePreview.querySelector("span") : null;
    const editImageInput = editForm ? editForm.querySelector(".product-image-upload") : null;
    const removeImageToggle = editForm ? editForm.querySelector(".product-image-remove-toggle") : null;
    const removeImageField = editForm ? editForm.querySelector('[name="remove_image"]') : null;
    const detailFields = [...productDetailDialog.querySelectorAll("[data-detail]")];
    let activeProductCard = null;
    let previewObjectUrl = null;

    if (detailImage && detailImageEmpty) {
        detailImage.addEventListener("error", () => {
            detailImage.hidden = true;
            detailImageEmpty.hidden = false;
        });
    }
    if (editImage && editImageEmpty) {
        editImage.addEventListener("error", () => {
            editImage.hidden = true;
            editImageEmpty.hidden = false;
        });
    }

    const openProductDetails = (card) => {
        activeProductCard = card;
        const trigger = card.dataset;
        if (deleteForm && deleteForm.elements && deleteForm.elements.product_code) {
            deleteForm.elements.product_code.value = trigger.code;
        }
        if (detailTitle) {
            detailTitle.textContent = trigger.name;
        }
        if (detailCode) {
            detailCode.textContent = `รหัสสินค้า ${trigger.code}`;
        }
        detailFields.forEach((field) => {
            field.textContent = trigger[field.dataset.detail] || "-";
        });
        if (detailImage && detailImageEmpty) {
            detailImage.hidden = !trigger.image;
            detailImageEmpty.hidden = Boolean(trigger.image);
            if (trigger.image) {
                detailImage.src = trigger.image;
            } else {
                detailImage.removeAttribute("src");
            }
        }
        productDetailDialog.showModal();
    };

    const setEditImagePreview = (imageUrl) => {
        if (!editImage || !editImageEmpty) return;
        editImage.hidden = !imageUrl;
        editImageEmpty.hidden = Boolean(imageUrl);
        if (imageUrl) {
            editImage.src = imageUrl;
        } else {
            editImage.removeAttribute("src");
        }
    };

    const resetEditImage = (imageUrl) => {
        if (editImageInput) editImageInput.value = "";
        if (removeImageToggle) removeImageToggle.checked = false;
        if (removeImageField) removeImageField.value = "0";
        if (previewObjectUrl) {
            URL.revokeObjectURL(previewObjectUrl);
            previewObjectUrl = null;
        }
        setEditImagePreview(imageUrl);
    };

    document.querySelectorAll(".product-detail-trigger").forEach((card) => {
        card.addEventListener("click", () => openProductDetails(card));
        card.addEventListener("keydown", (event) => {
            if (event.target === card && ["Enter", " "].includes(event.key)) {
                event.preventDefault();
                openProductDetails(card);
            }
        });
    });

    if (editButton && editDialog && editForm) {
        editButton.addEventListener("click", () => {
            const values = activeProductCard.dataset;
            if (editForm.elements.original_code) editForm.elements.original_code.value = values.code;
            if (editForm.elements.code) editForm.elements.code.value = values.code;
            if (editForm.elements.name) editForm.elements.name.value = values.name;
            if (editForm.elements.category) editForm.elements.category.value = values.category;
            if (editForm.elements.item_type) editForm.elements.item_type.value = values.type;
            if (editForm.elements.unit) editForm.elements.unit.value = values.unit;
            if (editForm.elements.location) editForm.elements.location.value = values.location;
            if (editForm.elements.reorder_point) editForm.elements.reorder_point.value = values.minimum;
            if (editImagePreview) editImagePreview.dataset.currentImage = values.image;
            resetEditImage(values.image);

            const hasCustom = values.hasCustomImage === "1";
            const removeWrap = removeImageToggle ? removeImageToggle.closest(".product-image-remove") : null;
            if (removeWrap) {
                removeWrap.hidden = !hasCustom;
            }

            const codeEl = editDialog.querySelector("#product-edit-code");
            if (codeEl) codeEl.textContent = `รหัสสินค้า ${values.code}`;
            productDetailDialog.close();
            editDialog.showModal();
        });

        if (editForm.elements.category) {
            editForm.elements.category.addEventListener("change", () => {
                const hasUploadedFile = editImageInput && editImageInput.files && editImageInput.files.length > 0;
                const hasCustom = activeProductCard && activeProductCard.dataset.hasCustomImage === "1";
                if (!hasUploadedFile && !hasCustom) {
                    const cat = editForm.elements.category.value || "สินค้าทั่วไป";
                    const catUrl = "image.php?cat=" + encodeURIComponent(cat);
                    if (editImagePreview) editImagePreview.dataset.currentImage = catUrl;
                    setEditImagePreview(catUrl);
                }
            });
        }

        if (editImageInput) {
            editImageInput.addEventListener("change", () => {
                const [file] = editImageInput.files;
                if (removeImageToggle) removeImageToggle.checked = false;
                if (previewObjectUrl) {
                    URL.revokeObjectURL(previewObjectUrl);
                    previewObjectUrl = null;
                }
                if (file) {
                    previewObjectUrl = URL.createObjectURL(file);
                    setEditImagePreview(previewObjectUrl);
                } else if (editImagePreview) {
                    setEditImagePreview(editImagePreview.dataset.currentImage);
                }
            });
        }

        if (removeImageToggle) {
            removeImageToggle.addEventListener("change", () => {
                if (removeImageToggle.checked) {
                    if (editImageInput) editImageInput.value = "";
                    setEditImagePreview("");
                } else if (editImagePreview) {
                    setEditImagePreview(editImagePreview.dataset.currentImage);
                }
            });
        }

        editForm.addEventListener("submit", () => {
            if (removeImageField && removeImageToggle) {
                removeImageField.value = removeImageToggle.checked ? "1" : "0";
            }
        });

        const backBtn = editDialog.querySelector("[data-back-to-product-details]");
        if (backBtn) {
            backBtn.addEventListener("click", () => {
                editDialog.close();
                productDetailDialog.showModal();
            });
        }

        const closeEditBtn = editDialog.querySelector("[data-close-edit-dialog]");
        if (closeEditBtn) {
            closeEditBtn.addEventListener("click", () => {
                editDialog.close();
            });
        }

        editDialog.addEventListener("click", (event) => {
            if (event.target === editDialog) {
                editDialog.close();
            }
        });

        if (editDialog.dataset.editCode) {
            const initialCard = [...document.querySelectorAll(".product-detail-trigger")]
                .find((card) => card.dataset.code === editDialog.dataset.editCode);
            if (initialCard) {
                activeProductCard = initialCard;
                const codeEl = editDialog.querySelector("#product-edit-code");
                if (codeEl) codeEl.textContent = `รหัสสินค้า ${initialCard.dataset.code}`;
                editDialog.showModal();
            }
        }
    }

    if (deleteForm) {
        deleteForm.addEventListener("submit", (event) => {
            const productName = activeProductCard ? activeProductCard.dataset.name : "สินค้านี้";
            const confirmed = window.confirm(
                `ยืนยันลบสินค้า "${productName}" หรือไม่?\nรายการนี้จะลบประวัติรับ–เบิกที่เกี่ยวข้องทั้งหมดและไม่สามารถกู้คืนได้`
            );
            if (!confirmed) {
                event.preventDefault();
            }
        });
    }

    productDetailDialog.querySelectorAll("[data-close-product-dialog]").forEach((button) => {
        button.addEventListener("click", () => productDetailDialog.close());
    });

    productDetailDialog.addEventListener("click", (event) => {
        if (event.target === productDetailDialog) {
            productDetailDialog.close();
        }
    });
}

document.querySelectorAll("#new-product .product-image-upload").forEach((input) => {
    const preview = input.closest(".product-image-upload-field").querySelector(".product-image-preview");
    const image = preview.querySelector("img");
    const placeholder = preview.querySelector("span");
    let objectUrl = null;
    input.addEventListener("change", () => {
        const [file] = input.files;
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
        if (file) {
            objectUrl = URL.createObjectURL(file);
            image.src = objectUrl;
            image.hidden = false;
            placeholder.hidden = true;
        } else {
            image.removeAttribute("src");
            image.hidden = true;
            placeholder.hidden = false;
        }
    });
});

const initProductCombobox = (combobox) => {
    const searchField = combobox.querySelector("[data-combobox-search]");
    const codeHidden = combobox.querySelector("[data-combobox-code]");
    const toggleButton = combobox.querySelector("[data-combobox-toggle]");
    const listBox = combobox.querySelector("[data-combobox-list]");
    const emptyNote = combobox.querySelector("[data-combobox-empty]");
    const options = [...combobox.querySelectorAll(".product-combobox-option")];
    const form = combobox.closest("form");
    const codeDisplay = form?.querySelector("[data-combobox-code-display]");
    const unitField = form?.querySelector("[data-combobox-unit-display]");
    if (!searchField || !codeHidden || !listBox) return;

    let highlightedIndex = -1;
    const getVisibleOptions = () => options.filter((opt) => !opt.hidden);

    const openList = () => {
        listBox.hidden = false;
        searchField.setAttribute("aria-expanded", "true");
    };

    const closeList = () => {
        listBox.hidden = true;
        searchField.setAttribute("aria-expanded", "false");
        highlightedIndex = -1;
        options.forEach((opt) => opt.classList.remove("highlighted"));
    };

    const selectOption = (option) => {
        codeHidden.value = option.dataset.code || "";
        searchField.value = option.dataset.name || "";
        searchField.setCustomValidity("");
        if (codeDisplay) codeDisplay.value = option.dataset.code || "";
        if (unitField) unitField.value = option.dataset.unit || "";
        options.forEach((opt) => opt.classList.toggle("selected", opt === option));
        closeList();
    };

    const filterOptions = (queryText) => {
        const query = queryText.trim().toLocaleLowerCase("th");
        let visibleCount = 0;
        options.forEach((opt) => {
            const matches = query === "" || (opt.dataset.search || "").includes(query);
            opt.hidden = !matches;
            opt.classList.remove("highlighted");
            if (matches) visibleCount++;
        });
        if (emptyNote) emptyNote.hidden = visibleCount > 0;
        highlightedIndex = -1;
    };

    searchField.addEventListener("focus", () => {
        filterOptions("");
        openList();
    });

    searchField.addEventListener("input", () => {
        searchField.setCustomValidity("");
        const typed = searchField.value.trim();
        const exactMatch = options.find(
            (opt) => opt.dataset.name === typed || opt.dataset.code === typed
        );
        if (!exactMatch) {
            codeHidden.value = "";
            if (codeDisplay) codeDisplay.value = "";
            if (unitField) unitField.value = "";
            options.forEach((opt) => opt.classList.remove("selected"));
        }
        filterOptions(typed);
        openList();
    });

    searchField.addEventListener("keydown", (event) => {
        const visible = getVisibleOptions();
        if (event.key === "ArrowDown") {
            event.preventDefault();
            if (listBox.hidden) openList();
            if (visible.length === 0) return;
            highlightedIndex = (highlightedIndex + 1) % visible.length;
            visible.forEach((opt, i) => opt.classList.toggle("highlighted", i === highlightedIndex));
            visible[highlightedIndex].scrollIntoView({ block: "nearest" });
        } else if (event.key === "ArrowUp") {
            event.preventDefault();
            if (listBox.hidden) openList();
            if (visible.length === 0) return;
            highlightedIndex = highlightedIndex <= 0 ? visible.length - 1 : highlightedIndex - 1;
            visible.forEach((opt, i) => opt.classList.toggle("highlighted", i === highlightedIndex));
            visible[highlightedIndex].scrollIntoView({ block: "nearest" });
        } else if (event.key === "Enter" && !listBox.hidden) {
            if (highlightedIndex >= 0 && visible[highlightedIndex]) {
                event.preventDefault();
                selectOption(visible[highlightedIndex]);
            } else if (visible.length === 1) {
                event.preventDefault();
                selectOption(visible[0]);
            }
        } else if (event.key === "Escape") {
            closeList();
        }
    });

    if (toggleButton) {
        toggleButton.addEventListener("click", () => {
            if (listBox.hidden) {
                filterOptions("");
                openList();
                searchField.focus();
            } else {
                closeList();
            }
        });
    }

    options.forEach((option) => {
        option.addEventListener("click", () => selectOption(option));
    });

    document.addEventListener("click", (event) => {
        if (!combobox.contains(event.target)) {
            closeList();
        }
    });

    if (form) {
        form.addEventListener("submit", (event) => {
            const allowCustom = combobox.hasAttribute("data-allow-custom") || combobox.dataset.allowCustom === "true";
            if (codeHidden.value === "") {
                const typed = searchField.value.trim().toLocaleLowerCase("th");
                const match = options.find(
                    (opt) =>
                        opt.dataset.name.toLocaleLowerCase("th") === typed ||
                        opt.dataset.code.toLocaleLowerCase("th") === typed
                );
                if (match) {
                    selectOption(match);
                } else if (!allowCustom) {
                    event.preventDefault();
                    searchField.setCustomValidity("กรุณาเลือกชื่อสินค้าจากรายการ");
                    searchField.reportValidity();
                    filterOptions(searchField.value);
                    openList();
                }
            }
        });
    }
};

document.querySelectorAll("[data-product-combobox]").forEach(initProductCombobox);

const historySearch = document.querySelector("#history-search");
const historyTabs = document.querySelectorAll("[data-history-tab]");
const historyRows = document.querySelectorAll(".history-row");
const receiptSection = document.querySelector("#history-section-receipts");
const issueSection = document.querySelector("#history-section-issues");
const historyResultCount = document.querySelector("#history-result-count");

if (historySearch || historyTabs.length > 0) {
    let currentTab = "all";
    const historyPageSize = 10;
    const currentPages = { receipt: 0, issue: 0 };

    const filterHistory = (resetPages = false) => {
        const query = historySearch ? historySearch.value.trim().toLocaleLowerCase("th") : "";
        if (resetPages) {
            currentPages.receipt = 0;
            currentPages.issue = 0;
        }

        const matchingRows = { receipt: [], issue: [] };
        historyRows.forEach((row) => {
            const rowType = row.dataset.type;
            const matchesQuery = query === "" || (row.dataset.search || "").includes(query);
            row.hidden = true;
            if (matchesQuery && matchingRows[rowType]) {
                matchingRows[rowType].push(row);
            }
        });

        Object.keys(matchingRows).forEach((type) => {
            const rows = matchingRows[type];
            const pageCount = Math.ceil(rows.length / historyPageSize);
            currentPages[type] = Math.min(currentPages[type], Math.max(0, pageCount - 1));
            const start = currentPages[type] * historyPageSize;
            rows.forEach((row, index) => {
                const visible = index >= start && index < start + historyPageSize;
                row.hidden = !visible;
            });

            const pagination = document.querySelector(`[data-history-pagination="${type}"]`);
            if (pagination) {
                const pageStatus = pagination.querySelector("[data-page-status]");
                const previousButton = pagination.querySelector('[data-page-direction="-1"]');
                const nextButton = pagination.querySelector('[data-page-direction="1"]');
                const sectionHidden = currentTab !== "all" && currentTab !== type;
                pagination.hidden = sectionHidden || pageCount <= 1;
                pageStatus.textContent = `หน้า ${currentPages[type] + 1} / ${pageCount}`;
                previousButton.disabled = currentPages[type] === 0;
                nextButton.disabled = currentPages[type] >= pageCount - 1;
            }
        });

        const visibleReceipts = currentTab === "issue" ? 0 : matchingRows.receipt.length;
        const visibleIssues = currentTab === "receipt" ? 0 : matchingRows.issue.length;

        if (receiptSection) {
            receiptSection.hidden = currentTab === "issue";
            const emptyEl = receiptSection.querySelector(".history-empty-search");
            if (emptyEl) {
                emptyEl.hidden = matchingRows.receipt.length > 0 || currentTab === "issue";
            }
            const countEl = receiptSection.querySelector(".section-visible-count");
            if (countEl) countEl.textContent = `${matchingRows.receipt.length} รายการ`;
        }

        if (issueSection) {
            issueSection.hidden = currentTab === "receipt";
            const emptyEl = issueSection.querySelector(".history-empty-search");
            if (emptyEl) {
                emptyEl.hidden = matchingRows.issue.length > 0 || currentTab === "receipt";
            }
            const countEl = issueSection.querySelector(".section-visible-count");
            if (countEl) countEl.textContent = `${matchingRows.issue.length} รายการ`;
        }

        if (historyResultCount) {
            historyResultCount.textContent = `แสดง ${visibleReceipts + visibleIssues} รายการ`;
        }
    };

    if (historySearch) {
        historySearch.addEventListener("input", () => filterHistory(true));

        document.addEventListener("keydown", (event) => {
            if (event.key === "/" && !["INPUT", "TEXTAREA"].includes(document.activeElement.tagName)) {
                event.preventDefault();
                historySearch.focus();
            }
        });
    }

    historyTabs.forEach((tab) => {
        tab.addEventListener("click", () => {
            currentTab = tab.dataset.historyTab;
            historyTabs.forEach((t) => t.classList.toggle("selected", t === tab));
            filterHistory(true);
        });
    });

    document.querySelectorAll("[data-history-pagination]").forEach((pagination) => {
        const type = pagination.dataset.historyPagination;
        pagination.querySelectorAll("[data-page-direction]").forEach((button) => {
            button.addEventListener("click", () => {
                currentPages[type] += Number(button.dataset.pageDirection);
                filterHistory();
            });
        });
    });

    filterHistory();
}

// --- Real-time Cross-Device Sync Engine ---
(function initCrossDeviceSync() {
    let lastVersion = null;
    const syncUrl = '../api/sync.php';

    async function checkSync() {
        try {
            const resp = await fetch(syncUrl + '?_=' + Date.now(), {
                cache: 'no-store',
                headers: { 'Accept': 'application/json' }
            });
            if (!resp.ok) return;
            const data = await resp.json();
            if (!data || !data.success || !data.version) return;

            if (lastVersion === null) {
                lastVersion = data.version;
                return;
            }

            if (data.version !== lastVersion) {
                lastVersion = data.version;
                const active = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
                const isFormInput = (active === 'input' || active === 'textarea' || active === 'select');
                if (!isFormInput) {
                    window.location.reload();
                } else {
                    showLiveSyncPill();
                }
            }
        } catch (e) {
            // Transient network failure ignored
        }
    }

    function showLiveSyncPill() {
        if (document.querySelector('#live-sync-pill')) return;
        const pill = document.createElement('div');
        pill.id = 'live-sync-pill';
        pill.innerHTML = 'มีข้อมูลใหม่จากเครื่องอื่น <button onclick="window.location.reload()" style="margin-left:8px;padding:3px 8px;border-radius:4px;border:none;background:#fff;color:#0758b4;font-weight:600;cursor:pointer;">รีเฟรช</button>';
        pill.style.cssText = 'position:fixed;bottom:20px;right:20px;background:#0758b4;color:#fff;padding:8px 14px;border-radius:20px;font-size:13px;box-shadow:0 4px 16px rgba(0,0,0,0.2);z-index:9999;display:flex;align-items:center;';
        document.body.appendChild(pill);
    }

    setInterval(checkSync, 3500);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            checkSync();
        }
    });
})();
