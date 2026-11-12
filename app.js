const searchInput = document.querySelector("#product-search");
const cards = [...document.querySelectorAll(".product-card")];
const filters = [...document.querySelectorAll(".filter-chip")];
const resultCount = document.querySelector("#result-count");
const emptySearch = document.querySelector("#empty-search");
let activeCategory = "all";

const systemDropdown = document.querySelector(".nav-dropdown");
if (systemDropdown) {
    const systemToggle = systemDropdown.querySelector(".nav-dropdown-btn");
    const mainNav = systemDropdown.closest(".main-nav");
    const setSystemDropdownOpen = (open) => {
        systemDropdown.classList.toggle("open", open);
        mainNav.classList.toggle("dropdown-open", open);
        systemToggle.setAttribute("aria-expanded", String(open));
    };

    systemToggle.addEventListener("click", () => {
        setSystemDropdownOpen(systemToggle.getAttribute("aria-expanded") !== "true");
    });

    document.addEventListener("click", (event) => {
        if (!systemDropdown.contains(event.target)) {
            setSystemDropdownOpen(false);
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && systemToggle.getAttribute("aria-expanded") === "true") {
            setSystemDropdownOpen(false);
            systemToggle.focus();
        }
    });
}

function filterProducts() {
    const query = searchInput.value.trim().toLocaleLowerCase("th");
    let visibleCount = 0;

    cards.forEach((card) => {
        const matchesQuery = card.dataset.search.includes(query);
        const matchesCategory = activeCategory === "all" || card.dataset.category === activeCategory;
        const visible = matchesQuery && matchesCategory;
        card.hidden = !visible;
        visibleCount += Number(visible);
    });

    resultCount.textContent = `แสดง ${visibleCount} รายการ`;
    emptySearch.hidden = visibleCount !== 0;
}

if (searchInput) {
    searchInput.addEventListener("input", filterProducts);

    filters.forEach((filter) => {
        filter.addEventListener("click", () => {
            activeCategory = filter.dataset.category;
            filters.forEach((item) => item.classList.toggle("selected", item === filter));
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
    const editForm = editDialog.querySelector("form");
    const editImagePreview = editForm.querySelector(".product-image-preview");
    const editImage = editImagePreview.querySelector("img");
    const editImageEmpty = editImagePreview.querySelector("span");
    const editImageInput = editForm.querySelector(".product-image-upload");
    const removeImageToggle = editForm.querySelector(".product-image-remove-toggle");
    const removeImageField = editForm.querySelector('[name="remove_image"]');
    const detailFields = [...productDetailDialog.querySelectorAll("[data-detail]")];
    let activeProductCard = null;
    let previewObjectUrl = null;

    detailImage.addEventListener("error", () => {
        detailImage.hidden = true;
        detailImageEmpty.hidden = false;
    });
    editImage.addEventListener("error", () => {
        editImage.hidden = true;
        editImageEmpty.hidden = false;
    });

    const openProductDetails = (card) => {
        activeProductCard = card;
        const trigger = card.dataset;
        deleteForm.elements.product_code.value = trigger.code;
        detailTitle.textContent = trigger.name;
        detailCode.textContent = `รหัสสินค้า ${trigger.code}`;
        detailFields.forEach((field) => {
            field.textContent = trigger[field.dataset.detail] || "-";
        });
        detailImage.hidden = !trigger.image;
        detailImageEmpty.hidden = Boolean(trigger.image);
        if (trigger.image) {
            detailImage.src = trigger.image;
        } else {
            detailImage.removeAttribute("src");
        }
        productDetailDialog.showModal();
    };

    const setEditImagePreview = (imageUrl) => {
        editImage.hidden = !imageUrl;
        editImageEmpty.hidden = Boolean(imageUrl);
        if (imageUrl) {
            editImage.src = imageUrl;
        } else {
            editImage.removeAttribute("src");
        }
    };

    const resetEditImage = (imageUrl) => {
        editImageInput.value = "";
        removeImageToggle.checked = false;
        removeImageField.value = "0";
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

    editButton.addEventListener("click", () => {
        const values = activeProductCard.dataset;
        editForm.elements.original_code.value = values.code;
        editForm.elements.code.value = values.code;
        editForm.elements.name.value = values.name;
        editForm.elements.category.value = values.category;
        editForm.elements.item_type.value = values.type;
        editForm.elements.unit.value = values.unit;
        editForm.elements.location.value = values.location;
        editForm.elements.reorder_point.value = values.minimum;
        editImagePreview.dataset.currentImage = values.image;
        resetEditImage(values.image);

        const hasCustom = values.hasCustomImage === "1";
        const removeWrap = removeImageToggle ? removeImageToggle.closest(".product-image-remove") : null;
        if (removeWrap) {
            removeWrap.hidden = !hasCustom;
        }

        editDialog.querySelector("#product-edit-code").textContent = `รหัสสินค้า ${values.code}`;
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
                editImagePreview.dataset.currentImage = catUrl;
                setEditImagePreview(catUrl);
            }
        });
    }

    deleteForm.addEventListener("submit", (event) => {
        const productName = activeProductCard.dataset.name;
        const confirmed = window.confirm(
            `ยืนยันลบสินค้า "${productName}" หรือไม่?\nรายการนี้จะลบประวัติรับ–เบิกที่เกี่ยวข้องทั้งหมดและไม่สามารถกู้คืนได้`
        );
        if (!confirmed) {
            event.preventDefault();
        }
    });

    editImageInput.addEventListener("change", () => {
        const [file] = editImageInput.files;
        removeImageToggle.checked = false;
        if (previewObjectUrl) {
            URL.revokeObjectURL(previewObjectUrl);
            previewObjectUrl = null;
        }
        if (file) {
            previewObjectUrl = URL.createObjectURL(file);
            setEditImagePreview(previewObjectUrl);
        } else {
            setEditImagePreview(editImagePreview.dataset.currentImage);
        }
    });

    removeImageToggle.addEventListener("change", () => {
        if (removeImageToggle.checked) {
            editImageInput.value = "";
            setEditImagePreview("");
        } else {
            setEditImagePreview(editImagePreview.dataset.currentImage);
        }
    });

    editForm.addEventListener("submit", () => {
        removeImageField.value = removeImageToggle.checked ? "1" : "0";
    });

    editDialog.querySelector("[data-back-to-product-details]").addEventListener("click", () => {
        editDialog.close();
        productDetailDialog.showModal();
    });

    editDialog.querySelector("[data-close-edit-dialog]").addEventListener("click", () => {
        editDialog.close();
    });

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
            editDialog.querySelector("#product-edit-code").textContent = `รหัสสินค้า ${initialCard.dataset.code}`;
            editDialog.showModal();
        }
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

const movementProduct = document.querySelector("#movement-product");
if (movementProduct) {
    const nameField = document.querySelector("#movement-product-name");
    const unitField = document.querySelector("#movement-product-unit");
    const updateMovementProduct = () => {
        const option = movementProduct.selectedOptions[0];
        nameField.value = option?.dataset.name ?? "";
        unitField.value = option?.dataset.unit ?? "";
    };
    movementProduct.addEventListener("change", updateMovementProduct);
    updateMovementProduct();
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
