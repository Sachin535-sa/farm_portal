// AgroNava - Premium Interactivity System
function initApp() {
    // Initializing micro-animations & modal mechanisms
    initializeModals();
    initializeMarketPriceChart();
    initializeMarketplaceFilters();
    initializeNotifications();
    initializeCommunityReporting();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initApp);
} else {
    initApp();
}

/**
 * Handles show, hide, and total amount calculation in ordering modals.
 */
function initializeModals() {
    const buyModal = document.getElementById("buy-modal");
    if (!buyModal) return;

    const closeBtn = buyModal.querySelector(".close-btn");
    const quantityInput = document.getElementById("order-quantity");
    const totalAmountSpan = document.getElementById("total-price");
    
    // Elements updated by clicking "Order Now" on a card
    const cropIdInput = document.getElementById("modal-crop-id");
    const cropNameHeader = document.getElementById("modal-crop-name");
    const maxQtySpan = document.getElementById("modal-max-qty");
    const warningDiv = document.getElementById("modal-stock-warning");
    
    let currentUnitPrice = 0;
    let maxQuantity = 0;

    // Listeners for Marketplace "Order Now" buttons
    document.querySelectorAll(".trigger-order-modal").forEach(button => {
        button.addEventListener("click", (e) => {
            e.preventDefault();
            
            const id = button.getAttribute("data-crop-id");
            const name = button.getAttribute("data-crop-name");
            const price = parseFloat(button.getAttribute("data-crop-price"));
            const maxQty = parseInt(button.getAttribute("data-crop-qty"));

            // Populate Modal Form Fields
            cropIdInput.value = id;
            cropNameHeader.textContent = `🌾 Order ${name}`;
            maxQtySpan.textContent = maxQty;
            currentUnitPrice = price;
            maxQuantity = maxQty;

            // Reset quantity to 1 and recalculate total
            quantityInput.value = 1;
            quantityInput.max = maxQty;
            calculateTotal(1, price);

            // Display initial stock level warnings in modal (Phase 13.4)
            if (warningDiv) {
                if (maxQty <= 20) {
                    warningDiv.style.display = "block";
                    warningDiv.style.background = "var(--danger-light)";
                    warningDiv.style.color = "var(--danger)";
                    warningDiv.style.border = "1px solid rgba(239, 68, 68, 0.15)";
                    warningDiv.innerHTML = `⚠️ <strong>SAFETY ALERT:</strong> This crop is running extremely low on stock (${maxQty} kg left!).`;
                } else {
                    warningDiv.style.display = "none";
                }
            }

            // Show Modal with Fade-In Overlay
            buyModal.style.display = "flex";
        });
    });

    // Recalculate price on quantity typing or clicking incrementors
    if (quantityInput) {
        quantityInput.addEventListener("input", () => {
            let val = parseInt(quantityInput.value) || 0;
            
            // Boundary checks
            if (val < 1) {
                val = 1;
                quantityInput.value = 1;
            } else if (val > maxQuantity) {
                val = maxQuantity;
                quantityInput.value = maxQuantity;
            }
            
            calculateTotal(val, currentUnitPrice);

            // Dynamic remaining stock check warning inside modal
            if (warningDiv) {
                const remainingStock = maxQuantity - val;
                if (remainingStock <= 20 && maxQuantity > 20) {
                    warningDiv.style.display = "block";
                    warningDiv.style.background = "rgba(245, 158, 11, 0.08)";
                    warningDiv.style.color = "#d97706";
                    warningDiv.style.border = "1px solid rgba(245, 158, 11, 0.2)";
                    warningDiv.innerHTML = `⚠️ <strong>STOCK DEPLETION NOTICE:</strong> Confirming this purchase will leave only ${remainingStock} kg in grower inventory.`;
                } else if (maxQuantity <= 20) {
                    // Retain low stock alert
                    warningDiv.style.display = "block";
                } else {
                    warningDiv.style.display = "none";
                }
            }
        });
    }

    function calculateTotal(qty, price) {
        const total = qty * price;
        if (totalAmountSpan) {
            totalAmountSpan.textContent = total.toLocaleString("en-IN", {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            });
        }
    }

    // Modal Hiding Actions
    if (closeBtn) {
        closeBtn.addEventListener("click", () => {
            buyModal.style.display = "none";
        });
    }

    window.addEventListener("click", (e) => {
        if (e.target === buyModal) {
            buyModal.style.display = "none";
        }
    });
}

/**
 * Handles drawing an interactive market price trend line chart using SVG.
 * Includes interactive mouse-hover to see historical price changes.
 */
function initializeMarketPriceChart() {
    const chart = document.getElementById("market-price-svg");
    if (!chart) return;

    const tooltipPrice = document.getElementById("tooltip-price");
    const tooltipDate = document.getElementById("tooltip-date");
    
    // Simulated weekly prices of a premium crop (e.g. Wheat)
    const pointsData = [
        { day: "Mon", price: 2100 },
        { day: "Tue", price: 2180 },
        { day: "Wed", price: 2150 },
        { day: "Thu", price: 2240 },
        { day: "Fri", price: 2310 },
        { day: "Sat", price: 2280 },
        { day: "Sun", price: 2350 }
    ];

    const width = 1000;
    const height = 200;
    const paddingLeft = 50;
    const paddingRight = 50;
    const paddingTop = 30;
    const paddingBottom = 30;

    const minPrice = 2000;
    const maxPrice = 2400;

    // Helper functions for SVG scaling
    const getX = (index) => paddingLeft + (index * (width - paddingLeft - paddingRight) / (pointsData.length - 1));
    const getY = (price) => height - paddingBottom - ((price - minPrice) * (height - paddingTop - paddingBottom) / (maxPrice - minPrice));

    // Construct path string
    let pathD = `M ${getX(0)} ${getY(pointsData[0].price)}`;
    let areaD = `M ${getX(0)} ${height - paddingBottom} L ${getX(0)} ${getY(pointsData[0].price)}`;

    for (let i = 1; i < pointsData.length; i++) {
        pathD += ` L ${getX(i)} ${getY(pointsData[i].price)}`;
        areaD += ` L ${getX(i)} ${getY(pointsData[i].price)}`;
    }
    areaD += ` L ${getX(pointsData.length - 1)} ${height - paddingBottom} Z`;

    // Apply paths to elements
    const linePath = document.getElementById("chart-line-path");
    const areaPath = document.getElementById("chart-area-path");
    const pointsGroup = document.getElementById("chart-points-group");

    if (linePath) linePath.setAttribute("d", pathD);
    if (areaPath) areaPath.setAttribute("d", areaD);

    if (pointsGroup) {
        pointsGroup.innerHTML = ""; // Clear existing
        
        pointsData.forEach((point, i) => {
            const cx = getX(i);
            const cy = getY(point.price);
            
            // Create interactive SVG circle element
            const circle = document.createElementNS("http://www.w3.org/2000/svg", "circle");
            circle.setAttribute("cx", cx);
            circle.setAttribute("cy", cy);
            circle.setAttribute("r", "5");
            circle.setAttribute("class", "chart-point");
            
            // Tooltip interactivity
            circle.addEventListener("mouseenter", () => {
                circle.setAttribute("r", "8");
                if (tooltipPrice) tooltipPrice.textContent = `₹${point.price}/Quintal`;
                if (tooltipDate) tooltipDate.textContent = `Trend for ${point.day}`;
            });
            
            circle.addEventListener("mouseleave", () => {
                circle.setAttribute("r", "5");
            });

            pointsGroup.appendChild(circle);

            // Add Text Labels for days below points
            const text = document.createElementNS("http://www.w3.org/2000/svg", "text");
            text.setAttribute("x", cx);
            text.setAttribute("y", height - 5);
            text.setAttribute("text-anchor", "middle");
            text.setAttribute("fill", "#64748b");
            text.setAttribute("font-size", "12");
            text.setAttribute("font-weight", "500");
            text.textContent = point.day;
            pointsGroup.appendChild(text);
        });
    }
}

function initializeMarketplaceFilters() {
    const searchInput = document.getElementById("marketplace-search");
    const sortSelect = document.getElementById("marketplace-sort");
    const pills = document.querySelectorAll(".category-pills .pill");
    const grid = document.querySelector(".marketplace-grid");
    const cards = document.querySelectorAll(".marketplace-grid .glass-card");

    if (!searchInput && pills.length === 0) return;

    let activeCategory = "All";
    let activeQuery = "";

    function filterListings() {
        cards.forEach(card => {
            const title = card.querySelector(".card-crop-name").textContent.toLowerCase();
            const category = card.getAttribute("data-category") || "";
            
            const matchesSearch = title.includes(activeQuery);
            const matchesCategory = activeCategory === "All" || category.toLowerCase() === activeCategory.toLowerCase();

            if (matchesSearch && matchesCategory) {
                card.style.display = "block";
                card.style.animation = "fadeIn 0.4s ease";
            } else {
                card.style.display = "none";
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener("input", (e) => {
            activeQuery = e.target.value.toLowerCase().trim();
            filterListings();
        });
    }

    pills.forEach(pill => {
        pill.addEventListener("click", () => {
            pills.forEach(p => p.classList.remove("active"));
            pill.classList.add("active");
            activeCategory = pill.getAttribute("data-category");
            filterListings();
        });
    });

    if (sortSelect && grid && cards.length > 0) {
        sortSelect.addEventListener("change", () => {
            const sortVal = sortSelect.value;
            const cardsArray = Array.from(cards);

            cardsArray.sort((a, b) => {
                let valA, valB;
                switch (sortVal) {
                    case "sales":
                        valA = parseInt(a.getAttribute("data-sales")) || 0;
                        valB = parseInt(b.getAttribute("data-sales")) || 0;
                        return valB - valA; // Descending
                    case "rating":
                        valA = parseFloat(a.getAttribute("data-rating")) || 0;
                        valB = parseFloat(b.getAttribute("data-rating")) || 0;
                        return valB - valA; // Descending
                    case "price-low":
                        valA = parseFloat(a.getAttribute("data-price")) || 0;
                        valB = parseFloat(b.getAttribute("data-price")) || 0;
                        return valA - valB; // Ascending
                    case "price-high":
                        valA = parseFloat(a.getAttribute("data-price")) || 0;
                        valB = parseFloat(b.getAttribute("data-price")) || 0;
                        return valB - valA; // Descending
                    case "default":
                    default:
                        valA = parseInt(a.getAttribute("data-index")) || 0;
                        valB = parseInt(b.getAttribute("data-index")) || 0;
                        return valA - valB; // Ascending
                }
            });

            // Re-append sorted cards
            cardsArray.forEach(card => {
                grid.appendChild(card);
                card.style.animation = "fadeIn 0.3s ease";
            });
        });
    }
}

/**
 * Handles dropdown toggles and async notification clear states.
 */
function initializeNotifications() {
    const bellBtn = document.getElementById("notif-bell-btn");
    const dropdown = document.getElementById("notif-dropdown-menu");
    
    if (!bellBtn || !dropdown) return;
    
    bellBtn.addEventListener("click", (e) => {
        dropdown.classList.toggle("show");
        e.stopPropagation();
    });
    
    document.addEventListener("click", (e) => {
        if (!bellBtn.contains(e.target)) {
            dropdown.classList.remove("show");
        }
    });
}

function markAllNotificationsRead(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    const pathPrefix = (window.location.pathname.includes('/buyer/') || window.location.pathname.includes('/farmer/')) ? '../' : '';
    
    fetch(pathPrefix + "config/mark_read.php")
        .then(response => response.json())
        .then(data => {
            if (data.status === "success") {
                const badge = document.querySelector(".notif-badge");
                if (badge) badge.style.display = "none";
                
                document.querySelectorAll(".notif-item").forEach(item => {
                    item.classList.remove("unread");
                });
                
                const clearBtn = document.querySelector(".notif-dropdown-header button");
                if (clearBtn) clearBtn.style.display = "none";
            }
        })
        .catch(err => console.error("Error clearing notifications:", err));
}

/**
 * Sends AJAX report listing requests to the backend system.
 */
function initializeCommunityReporting() {
    document.querySelectorAll(".report-listing-btn").forEach(btn => {
        btn.addEventListener("click", (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            if (!confirm("⚠️ Are you sure you want to report this crop listing as fake/spam? Listings with 3+ flags will be hidden automatically.")) {
                return;
            }
            
            const cropId = btn.getAttribute("data-crop-id");
            const pathPrefix = (window.location.pathname.includes('/buyer/') || window.location.pathname.includes('/farmer/')) ? '../' : '';
            
            fetch(pathPrefix + "buyer/report_listing.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: `crop_id=${cropId}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert("⚠️ Crop listing reported successfully! Thank you for maintaining AgroNava's high-quality standards.");
                    window.location.reload();
                } else {
                    alert("❌ Error reporting listing: " + (data.error || "Unknown error"));
                }
            })
            .catch(err => {
                console.error("Reporting failed:", err);
                alert("❌ Connection error. Please try again.");
            });
        });
    });
}

