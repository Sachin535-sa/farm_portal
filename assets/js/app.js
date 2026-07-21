// AgroNava - Premium Interactivity System
function initApp() {
    // Initializing micro-animations & modal mechanisms
    initializeModals();
    initializeMarketPriceChart();
    initializeMarketplaceFilters();
    initializeNotifications();
    initializeMobileNavbar();
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

    // Stepper & Slider configuration parameters
    let isFirstLoad = true;

    function initCheckoutMap() {
        const slider = document.getElementById("buyer-distance-slider");
        if (slider) {
            slider.value = 15;
            document.getElementById("buyer-distance-val").textContent = "15";
            document.getElementById("delivery-distance").value = "15";
            const buyerDistNode = document.getElementById("stepper-buyer-dist");
            if (buyerDistNode) buyerDistNode.textContent = "15.00 km";
        }
        triggerLivePricingUpdate();
    }

    function triggerLivePricingUpdate() {
        const qty = parseInt(quantityInput.value) || 1;
        const cropId = cropIdInput.value;
        const distance = parseFloat(document.getElementById("delivery-distance").value) || 15.0;
        const vehicle = document.getElementById("vehicle-type").value;
        const priority = document.getElementById("delivery-priority").value;
        const road = document.getElementById("road-condition").value;
        const location = document.getElementById("location-type").value;
        const weather = document.getElementById("weather-condition").value;
        const route_opt = document.getElementById("route-option").value;

        const ccSelect = document.getElementById("cc-routing-select");
        const whSelect = document.getElementById("wh-routing-select");
        const ccId = ccSelect ? ccSelect.value : "";
        const whId = whSelect ? whSelect.value : "";

        if (!cropId) return;

        const pathPrefix = (window.location.pathname.includes('/buyer/')) ? '' : 'buyer/';
        const url = `${pathPrefix}calculate_delivery.php?crop_id=${cropId}&quantity=${qty}&distance_km=${distance}&vehicle_type=${vehicle}&delivery_priority=${priority}&road_condition=${road}&location_type=${location}&weather=${weather}&route_option=${route_opt}&collection_center_id=${ccId}&warehouse_id=${whId}`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                // Auto recommend vehicle selection on first load
                if (isFirstLoad) {
                    document.getElementById("vehicle-type").value = data.recommended_vehicle;
                    if (ccSelect) ccSelect.value = data.collection_center_id;
                    if (whSelect) whSelect.value = data.warehouse_id;
                    isFirstLoad = false;
                    triggerLivePricingUpdate();
                    return;
                }

                // Show recommended badge if selection matches recommendation
                document.getElementById("rec-vehicle-badge").style.display = (data.vehicle_type === data.recommended_vehicle) ? "block" : "none";

                // Dynamically update stepper nodes
                const stepperCC = document.getElementById("stepper-cc-name");
                if (stepperCC && data.collection_center_name) {
                    stepperCC.textContent = data.collection_center_name;
                }
                const stepperCCDist = document.getElementById("stepper-cc-dist");
                if (stepperCCDist) {
                    stepperCCDist.textContent = parseFloat(data.distance_farm_to_cc || 0).toFixed(2) + " km";
                }

                const stepperWH = document.getElementById("stepper-wh-name");
                if (stepperWH && data.warehouse_name) {
                    stepperWH.textContent = data.warehouse_name;
                }
                const stepperWHDist = document.getElementById("stepper-wh-dist");
                if (stepperWHDist) {
                    stepperWHDist.textContent = parseFloat(data.distance_cc_to_wh || 0).toFixed(2) + " km";
                }

                const stepperBuyerDist = document.getElementById("stepper-buyer-dist");
                if (stepperBuyerDist) {
                    stepperBuyerDist.textContent = parseFloat(data.distance_wh_to_buyer || 0).toFixed(2) + " km";
                }

                const displayDist = document.getElementById("display-distance");
                if (displayDist) {
                    displayDist.textContent = parseFloat(data.total_distance_km || 0).toFixed(2);
                }

                // Populate DOM breakdown fields
                document.getElementById("breakdown-crop-val").textContent = Math.round(data.order_value).toLocaleString("en-IN");
                document.getElementById("breakdown-base-fee").textContent = Math.round(data.base_fee).toLocaleString("en-IN");
                document.getElementById("breakdown-distance-fee").textContent = Math.round(data.distance_cost).toLocaleString("en-IN");
                document.getElementById("breakdown-weight-fee").textContent = Math.round(data.weight_surcharge).toLocaleString("en-IN");
                document.getElementById("breakdown-vehicle-mult").textContent = data.vehicle_multiplier;
                const vehicleNameElem = document.getElementById("breakdown-vehicle-name");
                if (vehicleNameElem) {
                    vehicleNameElem.textContent = data.vehicle_display || data.vehicle_type;
                }
                document.getElementById("breakdown-fuel-fee").textContent = Math.round(data.fuel_adjustment).toLocaleString("en-IN");
                
                // Surcharges breakdown mapping
                const roadSurcharge = data.road_flat + (data.subtotal * (data.road_multiplier - 1.0));
                document.getElementById("breakdown-road-fee").textContent = Math.round(roadSurcharge).toLocaleString("en-IN");
                
                const zoneSurcharge = data.zone_flat + (data.subtotal * (data.zone_multiplier - 1.0));
                document.getElementById("breakdown-zone-fee").textContent = Math.round(zoneSurcharge).toLocaleString("en-IN");
                
                const weatherSurcharge = data.weather_flat + (data.subtotal * (data.weather_multiplier - 1.0));
                document.getElementById("breakdown-seasonal-fee").textContent = Math.round(weatherSurcharge).toLocaleString("en-IN");
                
                const tollsPkgFee = data.toll_charges + data.packaging_fee;
                document.getElementById("breakdown-tolls-pkg").textContent = Math.round(tollsPkgFee).toLocaleString("en-IN");
                
                document.getElementById("breakdown-carbon").textContent = data.carbon_footprint_kg.toFixed(2);
                document.getElementById("breakdown-raw-delivery").textContent = Math.round(data.raw_calculated).toLocaleString("en-IN");
                document.getElementById("breakdown-final-delivery").textContent = Math.round(data.final_delivery_fee).toLocaleString("en-IN");
                document.getElementById("breakdown-eta").textContent = data.estimated_delivery_time;

                // Check badges
                const minBadge = document.getElementById("min-fee-badge");
                if (minBadge) {
                    minBadge.style.display = data.minimum_enforced ? "block" : "none";
                }

                const freeBadge = document.getElementById("free-delivery-badge");
                if (freeBadge) {
                    freeBadge.style.display = data.free_delivery_applied ? "block" : "none";
                }

                // Overall price
                if (totalAmountSpan) {
                    totalAmountSpan.textContent = Math.round(data.grand_total).toLocaleString("en-IN");
                }
            })
            .catch(err => console.error("Pricing API Error:", err));
    }

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

            // Reset inputs to default
            quantityInput.value = 1;
            quantityInput.max = maxQty;
            isFirstLoad = true; // reset first load flag for recommending vehicle

            // Show Modal
            buyModal.style.display = "flex";

            // Initialize/Fit Map after modal overlay transition
            setTimeout(() => {
                initCheckoutMap();
            }, 150);

            // Trigger initial stock alerts
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
        });
    });

    // Form inputs monitoring for live price updates
    const inputsToWatch = [
        quantityInput,
        document.getElementById("vehicle-type"),
        document.getElementById("delivery-priority"),
        document.getElementById("road-condition"),
        document.getElementById("location-type"),
        document.getElementById("weather-condition"),
        document.getElementById("route-option"),
        document.getElementById("cc-routing-select"),
        document.getElementById("wh-routing-select")
    ];

    inputsToWatch.forEach(input => {
        if (input) {
            input.addEventListener("change", triggerLivePricingUpdate);
            input.addEventListener("input", triggerLivePricingUpdate);
        }
    });

    const distSlider = document.getElementById("buyer-distance-slider");
    if (distSlider) {
        distSlider.addEventListener("input", function() {
            const val = parseFloat(this.value).toFixed(1);
            const valText = parseFloat(this.value).toFixed(0);
            document.getElementById("buyer-distance-val").textContent = valText;
            document.getElementById("delivery-distance").value = val;
            const buyerDistNode = document.getElementById("stepper-buyer-dist");
            if (buyerDistNode) buyerDistNode.textContent = val + " km";
            triggerLivePricingUpdate();
        });
    }

    // Recalculate and trigger stock warnings on quantity change
    if (quantityInput) {
        quantityInput.addEventListener("input", () => {
            let val = parseInt(quantityInput.value) || 0;
            if (val < 1) {
                val = 1;
                quantityInput.value = 1;
            } else if (val > maxQuantity) {
                val = maxQuantity;
                quantityInput.value = maxQuantity;
            }

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
                    warningDiv.style.display = "block";
                } else {
                    warningDiv.style.display = "none";
                }
            }
        });
    }

    // Modal Hiding Actions
    if (closeBtn) {
        closeBtn.addEventListener("click", () => {
            buyModal.style.display = "none";
            if (checkoutMap && window.staticRoutingLine) {
                checkoutMap.removeLayer(window.staticRoutingLine);
                window.staticRoutingLine = null;
            }
        });
    }

    window.addEventListener("click", (e) => {
        if (e.target === buyModal) {
            buyModal.style.display = "none";
            if (checkoutMap && window.staticRoutingLine) {
                checkoutMap.removeLayer(window.staticRoutingLine);
                window.staticRoutingLine = null;
            }
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

function initializeMobileNavbar() {
    const toggleBtn = document.getElementById("navbar-toggle-btn");
    const menu = document.getElementById("navbar-menu-container");
    
    if (!toggleBtn || !menu) return;
    
    toggleBtn.addEventListener("click", (e) => {
        menu.classList.toggle("active");
        e.stopPropagation();
    });
    
    document.addEventListener("click", (e) => {
        if (!toggleBtn.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.remove("active");
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


/**
 * Toggles wishlist state for product cards.
 */
function toggleWishlist(btn, cropId) {
    if (window.event) {
        window.event.preventDefault();
        window.event.stopPropagation();
    }
    
    btn.classList.toggle('active');
    
    // Animate pop
    btn.style.transform = 'scale(1.3)';
    setTimeout(() => {
        btn.style.transform = '';
    }, 200);
}
