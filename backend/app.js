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
    const refreshButton = document.querySelector("#mobile-refresh-button");
    const closeBtn = document.querySelector("#sidebar-close-btn");

    function openSidebar() {
        if (sidebar) sidebar.classList.add("open");
        if (backdrop) backdrop.classList.add("active");
        if (mobileToggle) {
            mobileToggle.setAttribute("aria-expanded", "true");
            mobileToggle.setAttribute("aria-label", "ปิดเมนู");
        }
        document.body.style.overflow = "hidden";
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove("open");
        if (backdrop) backdrop.classList.remove("active");
        if (mobileToggle) {
            mobileToggle.setAttribute("aria-expanded", "false");
            mobileToggle.setAttribute("aria-label", "เปิดเมนู");
        }
        document.body.style.overflow = "";
    }

    if (mobileToggle) mobileToggle.addEventListener("click", openSidebar);
    if (refreshButton) refreshButton.addEventListener("click", () => window.location.reload());
    if (closeBtn) closeBtn.addEventListener("click", closeSidebar);
    if (backdrop) backdrop.addEventListener("click", closeSidebar);

    let touchStart = null;
    document.addEventListener("touchstart", (event) => {
        const touch = event.changedTouches[0];
        if (touch) touchStart = { x: touch.clientX, y: touch.clientY, target: event.target };
    }, { passive: true });

    document.addEventListener("touchend", (event) => {
        const start = touchStart;
        touchStart = null;
        const touch = event.changedTouches[0];
        if (!start || !touch || !window.matchMedia("(max-width: 960px)").matches) return;

        const deltaX = touch.clientX - start.x;
        const deltaY = touch.clientY - start.y;
        if (Math.abs(deltaX) < 70 || Math.abs(deltaX) < Math.abs(deltaY) * 1.25) return;

        const target = start.target instanceof Element ? start.target : null;
        const isSidebarOpen = sidebar && sidebar.classList.contains("open");
        if (isSidebarOpen) {
            if (deltaX < 0 && target && sidebar.contains(target)) closeSidebar();
            return;
        }

        if (deltaX < 0) {
            if (target && target.closest("a, button, input, textarea, select, [contenteditable='true'], .table-scroll, .category-filters")) return;
            openSidebar();
        } else if (start.x <= 32) {
            openSidebar();
        }
    }, { passive: true });
    document.addEventListener("touchcancel", () => {
        touchStart = null;
    }, { passive: true });

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
        const matchesQuery = (card.dataset.search || "").toLocaleLowerCase("th").includes(query);
        const cardCategories = (card.dataset.categories || card.dataset.category || "").split("|").filter(Boolean);
        const matchesCategory = activeCategory === "all" || cardCategories.includes(activeCategory);
        const matchesStatus = activeStatus === "all"
            || (activeStatus === "borrowed" ? card.dataset.borrowed === "1" : card.dataset.statusKey === activeStatus);
        const visible = matchesQuery && matchesCategory && matchesStatus;
        card.hidden = !visible;
        visibleCount += Number(visible);
    });

    resultCount.textContent = `แสดง ${visibleCount} รายการ`;
    if (visibleCount === 0) {
        const statusLabelMap = { normal: "ปกติ", low: "ควรสั่งซื้อ", negative: "หมด", borrowed: "กำลังยืม" };
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

    const productSort = document.querySelector("#product-sort");
    const productGrid = document.querySelector("#product-grid");
    if (productSort && productGrid) {
        productSort.addEventListener("change", () => {
            const mode = productSort.value;
            const sorted = [...cards].sort((a, b) => {
                const orderDiff = Number(a.dataset.order || 0) - Number(b.dataset.order || 0);
                if (mode === "newest") {
                    const tsDiff = Number(b.dataset.latestTs || 0) - Number(a.dataset.latestTs || 0);
                    if (tsDiff !== 0) return tsDiff;
                    const idDiff = Number(b.dataset.latestId || 0) - Number(a.dataset.latestId || 0);
                    return idDiff !== 0 ? idDiff : -orderDiff;
                }
                if (mode === "oldest") {
                    const tsDiff = Number(a.dataset.earliestTs || 0) - Number(b.dataset.earliestTs || 0);
                    if (tsDiff !== 0) return tsDiff;
                    const idDiff = Number(a.dataset.earliestId || 0) - Number(b.dataset.earliestId || 0);
                    return idDiff !== 0 ? idDiff : orderDiff;
                }
                if (mode === "stock-desc") {
                    const diff = Number(b.dataset.stockNum || 0) - Number(a.dataset.stockNum || 0);
                    return diff !== 0 ? diff : orderDiff;
                }
                if (mode === "stock-asc") {
                    const diff = Number(a.dataset.stockNum || 0) - Number(b.dataset.stockNum || 0);
                    return diff !== 0 ? diff : orderDiff;
                }
                return orderDiff;
            });
            const frag = document.createDocumentFragment();
            sorted.forEach((card) => frag.appendChild(card));
            if (emptySearch && emptySearch.parentNode === productGrid) {
                productGrid.insertBefore(frag, emptySearch);
            } else {
                productGrid.appendChild(frag);
            }
        });
    }

    document.addEventListener("keydown", (event) => {
        if (event.key === "/" && !["INPUT", "TEXTAREA"].includes(document.activeElement.tagName)) {
            event.preventDefault();
            searchInput.focus();
        }
    });
}

// Multi-select Checkbox Dropdown (GL Description)
function updateMultiDropdownLabel(dropdown) {
    const labelEl = dropdown.querySelector("[data-multi-label]");
    if (!labelEl) return;
    const checked = [...dropdown.querySelectorAll('input[type="checkbox"]:checked')].map((cb) => cb.value);
    const placeholder = dropdown.dataset.placeholder || "เลือกหมวดหมู่...";
    labelEl.textContent = checked.length > 0 ? checked.join(", ") : placeholder;
    dropdown.classList.toggle("has-value", checked.length > 0);
}

function syncMultiDropdown(dropdown, rawValue) {
    if (!dropdown) return;
    const selectedSet = new Set(
        String(rawValue || "")
            .split(",")
            .map((v) => v.trim())
            .filter(Boolean)
    );
    dropdown.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
        cb.checked = selectedSet.has(cb.value);
    });
    updateMultiDropdownLabel(dropdown);
}

(function initMultiDropdowns() {
    const dropdowns = [...document.querySelectorAll("[data-multi-dropdown]")];
    if (dropdowns.length === 0) return;

    const closeAll = (except = null) => {
        dropdowns.forEach((dd) => {
            if (dd === except) return;
            const menu = dd.querySelector("[data-multi-menu]");
            const toggle = dd.querySelector("[data-multi-toggle]");
            if (menu) menu.hidden = true;
            if (toggle) toggle.setAttribute("aria-expanded", "false");
            dd.classList.remove("open");
        });
    };

    dropdowns.forEach((dd) => {
        const toggle = dd.querySelector("[data-multi-toggle]");
        const menu = dd.querySelector("[data-multi-menu]");
        updateMultiDropdownLabel(dd);

        if (toggle && menu) {
            toggle.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();
                const willOpen = menu.hidden;
                closeAll(willOpen ? dd : null);
                menu.hidden = !willOpen;
                toggle.setAttribute("aria-expanded", String(willOpen));
                dd.classList.toggle("open", willOpen);
            });
        }

        dd.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
            cb.addEventListener("change", () => updateMultiDropdownLabel(dd));
        });
    });

    document.addEventListener("click", (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target || !target.closest("[data-multi-dropdown]")) {
            closeAll();
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeAll();
        }
    });
})();

const productDetailDialog = document.querySelector("#product-detail-dialog");
if (productDetailDialog) {
    const detailTitle = document.querySelector("#product-detail-title");
    const detailCode = document.querySelector("#product-detail-code");
    const detailBorrowed = document.querySelector("#product-detail-borrowed");
    const detailBorrowLink = document.querySelector("#product-detail-borrow");
    const detailIssueLink = document.querySelector("#product-detail-issue");
    const detailAccountToggle = document.querySelector("#product-detail-account-toggle");
    const detailAccountCount = document.querySelector("#product-detail-account-count");
    const detailAccountList = document.querySelector("#product-detail-account-list");
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
    let activeVariants = [];
    let activeVariant = null;
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

    const renderActiveVariant = (variant, totalStock) => {
        activeVariant = variant;
        if (deleteForm && deleteForm.elements && deleteForm.elements.product_code) {
            deleteForm.elements.product_code.value = variant.code;
        }
        if (detailTitle) {
            detailTitle.textContent = variant.name;
        }
        if (detailCode) {
            detailCode.textContent = `Stock Code ${variant.stockCode || "-"}`;
        }
        if (detailBorrowed) {
            const vBorrowedQty = Number(variant.borrowedQty || 0);
            const totalBorrowedQty = activeProductCard ? Number(activeProductCard.dataset.borrowedQty || 0) : vBorrowedQty;
            detailBorrowed.replaceChildren();
            if (vBorrowedQty > 0) {
                const dot = document.createElement("i");
                const txt = variant.borrowedBorrowers
                    ? `กำลังยืมอยู่ · โดย ${variant.borrowedBorrowers}`
                    : "กำลังยืมอยู่";
                detailBorrowed.append(dot, document.createTextNode(txt));
                detailBorrowed.hidden = false;
            } else if (totalBorrowedQty > 0) {
                const dot = document.createElement("i");
                detailBorrowed.append(dot, document.createTextNode(`กำลังยืมอยู่ใน Stock Code นี้ ${totalBorrowedQty} รายการ`));
                detailBorrowed.hidden = false;
            } else {
                detailBorrowed.hidden = true;
            }
        }
        if (detailBorrowLink) {
            const scParam = variant.stockCode && variant.stockCode !== "-"
                ? `&stock_code=${encodeURIComponent(variant.stockCode)}`
                : "";
            detailBorrowLink.href = `borrow.php?borrow_product=${encodeURIComponent(variant.code || "")}${scParam}`;
        }
        if (detailIssueLink) {
            detailIssueLink.href = `issue.php?product=${encodeURIComponent(variant.code || "")}`;
        }
        const hideIfEmptyKeys = new Set(["calNo", "calDate", "calExp"]);
        detailFields.forEach((field) => {
            const key = field.dataset.detail;
            const rawVal = String(variant[key] ?? "").trim();
            if (hideIfEmptyKeys.has(key)) {
                const row = field.closest("div");
                const isEmpty = rawVal === "" || rawVal === "-" || rawVal === "—";
                if (row) {
                    row.hidden = isEmpty;
                    row.style.display = isEmpty ? "none" : "";
                }
                field.textContent = isEmpty ? "" : rawVal;
                return;
            }
            if (key === "stock" && activeVariants.length > 1) {
                field.textContent = `${totalStock} (ตัวนี้ ${variant.stock})`;
            } else {
                field.textContent = variant[key] || "-";
            }
        });
        if (detailImage && detailImageEmpty) {
            detailImage.hidden = !variant.image;
            detailImageEmpty.hidden = Boolean(variant.image);
            if (variant.image) {
                detailImage.src = variant.image;
            } else {
                detailImage.removeAttribute("src");
            }
        }
        if (detailAccountList) {
            detailAccountList.querySelectorAll(".account-code-item-btn").forEach((btn) => {
                btn.classList.toggle("active", btn.dataset.variantCode === variant.code);
            });
        }
    };

    const renderAccountCodeList = (variants, totalStock) => {
        if (!detailAccountList || !detailAccountToggle || !detailAccountCount) return;
        detailAccountList.replaceChildren();
        detailAccountList.hidden = true;
        detailAccountToggle.setAttribute("aria-expanded", "false");

        if (variants.length > 1) {
            detailAccountCount.textContent = `${variants.length} ▾`;
            detailAccountCount.hidden = false;
            detailAccountToggle.disabled = false;
            detailAccountToggle.classList.add("has-multiple");
            detailAccountToggle.title = "คลิกเพื่อดูรายการ Account Code1 ทั้งหมดของ Stock Code นี้";
        } else {
            detailAccountCount.textContent = "";
            detailAccountCount.hidden = true;
            detailAccountToggle.disabled = true;
            detailAccountToggle.classList.remove("has-multiple");
            detailAccountToggle.removeAttribute("title");
            return;
        }

        const header = document.createElement("div");
        header.className = "account-code-list-title";
        header.textContent = `รายการ Account Code1 ของ Stock Code นี้ (${variants.length} รายการ)`;
        detailAccountList.append(header);

        const listWrap = document.createElement("div");
        listWrap.className = "account-code-list-items";
        variants.forEach((v) => {
            const btn = document.createElement("button");
            btn.type = "button";
            btn.className = "account-code-item-btn";
            btn.dataset.variantCode = v.code;

            const isBorrowed = Number(v.borrowedQty || 0) > 0;
            if (isBorrowed) {
                btn.classList.add("is-borrowed");
            }

            const headWrap = document.createElement("div");
            headWrap.className = "account-code-item-head";

            const codeSpan = document.createElement("strong");
            codeSpan.textContent = v.accountCode1 || "-";
            headWrap.append(codeSpan);

            if (isBorrowed) {
                const tag = document.createElement("span");
                tag.className = "account-code-borrowed-tag";
                tag.textContent = "กำลังยืมอยู่";
                headWrap.append(tag);
            }

            const metaSpan = document.createElement("small");
            const metaParts = [`คงเหลือ ${v.stock} ${v.unit || ""}`.trim()];
            if (v.apex) metaParts.push(`Apex ${v.apex}`);
            if (isBorrowed && v.borrowedBorrowers) metaParts.push(`ผู้ยืม: ${v.borrowedBorrowers}`);
            metaSpan.textContent = metaParts.join(" · ");

            btn.append(headWrap, metaSpan);
            btn.addEventListener("click", () => {
                renderActiveVariant(v, totalStock);
            });
            listWrap.append(btn);
        });
        detailAccountList.append(listWrap);
    };

    if (detailAccountToggle && detailAccountList) {
        detailAccountToggle.addEventListener("click", () => {
            if (activeVariants.length <= 1) return;
            const willOpen = detailAccountList.hidden;
            detailAccountList.hidden = !willOpen;
            detailAccountToggle.setAttribute("aria-expanded", String(willOpen));
        });
    }

    const openProductDetails = (card) => {
        activeProductCard = card;
        const trigger = card.dataset;
        let parsedVariants = [];
        try {
            parsedVariants = trigger.variants ? JSON.parse(trigger.variants) : [];
        } catch (e) {
            parsedVariants = [];
        }
        if (!Array.isArray(parsedVariants) || parsedVariants.length === 0) {
            parsedVariants = [{ ...trigger }];
        }
        activeVariants = parsedVariants;
        const totalStock = trigger.totalStock || trigger.stock || "0";
        renderAccountCodeList(activeVariants, totalStock);
        renderActiveVariant(activeVariants[0], totalStock);
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
            const values = activeVariant || activeProductCard.dataset;
            if (editForm.elements.original_code) editForm.elements.original_code.value = values.code;
            if (editForm.elements.code) editForm.elements.code.value = values.code;
            if (editForm.elements.name) editForm.elements.name.value = values.name;
            [["account_code1", "accountCode1"], ["stock_code", "stockCode"], ["section_code", "sectionCode"], ["section_name", "sectionName"], ["apex", "apex"], ["notes", "notes"], ["cal_no", "calNo"], ["cal_date", "calDate"], ["cal_exp", "calExp"]].forEach(([field, key]) => {
                if (editForm.elements[field]) editForm.elements[field].value = values[key] || "";
            });
            const editGlDropdown = editForm.querySelector("#edit-gl-description-dropdown");
            if (editGlDropdown) {
                syncMultiDropdown(editGlDropdown, values.glDescription || values.category || "");
            }
            if (editForm.elements.category) editForm.elements.category.value = values.category;
            if (editForm.elements.item_type) editForm.elements.item_type.value = values.type;
            if (editForm.elements.unit) editForm.elements.unit.value = values.unit;
            if (editForm.elements.location) editForm.elements.location.value = values.location;
            if (editForm.elements.reorder_point) editForm.elements.reorder_point.value = String(values.minimum || "").replace(/,/g, "");
            if (editForm.elements.buy_date) editForm.elements.buy_date.value = values.buyDateInput || "";
            if (editForm.elements.buy_date_time) editForm.elements.buy_date_time.value = values.buyDateTimeInput || "";
            if (editForm.elements.buy_price) editForm.elements.buy_price.value = values.buyPriceRaw || "";
            if (editImagePreview) editImagePreview.dataset.currentImage = values.image;
            resetEditImage(values.image);

            const hasCustom = values.hasCustomImage === "1";
            const removeWrap = removeImageToggle ? removeImageToggle.closest(".product-image-remove") : null;
            if (removeWrap) {
                removeWrap.hidden = !hasCustom;
            }

            const codeEl = editDialog.querySelector("#product-edit-code");
            if (codeEl) codeEl.textContent = `Stock Code ${values.stockCode || "-"}`;
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
        if (codeDisplay) codeDisplay.value = option.dataset.stockCode || option.dataset.code || "";
        if (unitField) unitField.value = option.dataset.unit || "";
        options.forEach((opt) => opt.classList.toggle("selected", opt === option));
        closeList();
    };

    const filterOptions = (queryText) => {
        const query = queryText.trim().toLocaleLowerCase("th");
        let visibleCount = 0;
        options.forEach((opt) => {
            const haystack = (opt.dataset.search || "").toLocaleLowerCase("th");
            const matches = query === "" || haystack.includes(query);
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
        const typedLower = typed.toLocaleLowerCase("th");
        const exactMatch = options.find(
            (opt) =>
                (opt.dataset.name || "").toLocaleLowerCase("th") === typedLower ||
                (opt.dataset.code || "").toLocaleLowerCase("th") === typedLower ||
                (opt.dataset.stockCode || "").toLocaleLowerCase("th") === typedLower ||
                ((opt.dataset.accountCode1 || "") !== "" && (opt.dataset.accountCode1 || "") !== "-" && (opt.dataset.accountCode1 || "").toLocaleLowerCase("th") === typedLower)
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
                        (opt.dataset.name || "").toLocaleLowerCase("th") === typed ||
                        (opt.dataset.code || "").toLocaleLowerCase("th") === typed ||
                        (opt.dataset.stockCode || "").toLocaleLowerCase("th") === typed ||
                        ((opt.dataset.accountCode1 || "") !== "" && (opt.dataset.accountCode1 || "") !== "-" && (opt.dataset.accountCode1 || "").toLocaleLowerCase("th") === typed)
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

const issueHistorySearch = document.querySelector("#issue-history-search");
if (issueHistorySearch) {
    const issueRows = [...document.querySelectorAll(".issue-history-row")];
    const issueEmpty = document.querySelector("#issue-history-empty");
    const filterIssueHistory = () => {
        const query = issueHistorySearch.value.trim().toLocaleLowerCase("th");
        let visible = 0;
        issueRows.forEach((row) => {
            const haystack = (row.dataset.search || "").toLocaleLowerCase("th");
            const matches = query === "" || haystack.includes(query);
            row.hidden = !matches;
            if (matches) visible++;
        });
        if (issueEmpty) {
            issueEmpty.hidden = !(issueRows.length > 0 && visible === 0);
        }
    };
    issueHistorySearch.addEventListener("input", filterIssueHistory);
    document.addEventListener("keydown", (event) => {
        if (event.key === "/" && !["INPUT", "TEXTAREA", "SELECT"].includes(document.activeElement.tagName)) {
            event.preventDefault();
            issueHistorySearch.focus();
        }
    });
}

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
            const matchesQuery = query === "" || (row.dataset.search || "").toLocaleLowerCase("th").includes(query);
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

// Prevent live-sync auto-reload from interrupting an in-flight form submission
document.addEventListener('submit', (event) => {
    if (!event.defaultPrevented) {
        window.__storeFormSubmitting = true;
    }
});

// --- Real-time Cross-Device Sync Engine ---
(function initCrossDeviceSync() {
    let lastVersion = null;
    const syncUrl = (window.location.pathname.includes('/web/') || window.location.pathname.includes('/mobile/')) ? '../api/sync.php' : 'api/sync.php';

    async function checkSync() {
        if (window.__storeFormSubmitting) return;
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
                if (window.__storeFormSubmitting) return;
                const active = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
                const isFormInput = (active === 'input' || active === 'textarea' || active === 'select');
                const hasOpenDialog = Boolean(document.querySelector('dialog[open]'));
                if (!isFormInput && !hasOpenDialog) {
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

