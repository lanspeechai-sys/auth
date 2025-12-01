// Products JavaScript - Handle AJAX requests and UI interactions

let currentPage = 1;
let currentFilter = null;
let currentQuery = null;
let isLoading = false;

// Show/hide loading indicator
function showLoading() {
    document.getElementById('loading').style.display = 'block';
    isLoading = true;
}

function hideLoading() {
    document.getElementById('loading').style.display = 'none';
    isLoading = false;
}

// Update products container with new data
function updateProductsContainer(products) {
    const container = document.getElementById('productsContainer');
    
    if (!products || products.length === 0) {
        container.innerHTML = `
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="bi bi-box" style="font-size: 4rem; color: #6c757d; opacity: 0.5;"></i>
                    <h4 class="mt-3 text-muted">No Products Found</h4>
                    <p class="text-muted">Try adjusting your search or filter criteria.</p>
                </div>
            </div>
        `;
        return;
    }
    
    let html = '';
    products.forEach(product => {
        const imageHtml = product.image_path 
            ? `<img src="uploads/${product.image_path}" class="product-image" alt="${product.title}">`
            : `<div class="product-placeholder"><i class="bi bi-box"></i></div>`;
            
        const categoryBadge = product.category_name 
            ? `<span class="badge bg-light text-dark border">${product.category_name}</span>`
            : '';
            
        const brandBadge = product.brand_name 
            ? `<span class="badge bg-secondary">${product.brand_name}</span>`
            : '';
            
        const description = product.description 
            ? (product.description.length > 120 ? product.description.substring(0, 120) + '...' : product.description)
            : '';
            
        html += `
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card product-card">
                    ${imageHtml}
                    
                    <div class="card-body d-flex flex-column">
                        <div class="product-badges">
                            ${categoryBadge}
                            ${brandBadge}
                        </div>
                        
                        <h5 class="card-title fw-bold">
                            <a href="single_product.php?id=${product.id}" class="text-decoration-none">
                                ${product.title}
                            </a>
                        </h5>
                        
                        ${description ? `<p class="card-text text-muted flex-grow-1">${description}</p>` : ''}
                        
                        <div class="mt-auto">
                            <div class="product-meta mb-3">
                                <span class="price-tag">$${parseFloat(product.price).toFixed(2)}</span>
                                <small class="text-muted">
                                    <i class="bi bi-calendar"></i> 
                                    ${new Date(product.created_at).toLocaleDateString('en-US', { 
                                        month: 'short', 
                                        day: 'numeric', 
                                        year: 'numeric' 
                                    })}
                                </small>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex">
                                <a href="single_product.php?id=${product.id}" 
                                   class="btn btn-outline-primary flex-fill">
                                    <i class="bi bi-eye"></i> View Details
                                </a>
                                <button class="btn add-to-cart-btn flex-fill" 
                                        onclick="addToCart(${product.id})">
                                    <i class="bi bi-cart-plus"></i> Add to Cart
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Update pagination
function updatePagination(totalPages, currentPageNum) {
    const pagination = document.getElementById('pagination');
    if (!pagination || totalPages <= 1) {
        if (pagination) pagination.style.display = 'none';
        return;
    }
    
    pagination.style.display = 'block';
    let paginationHTML = '';
    
    // Previous button
    paginationHTML += `
        <li class="page-item ${currentPageNum === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPageNum - 1}); return false;">Previous</a>
        </li>
    `;
    
    // Page numbers
    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentPageNum - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
    
    if (endPage - startPage < maxVisiblePages - 1) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }
    
    if (startPage > 1) {
        paginationHTML += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(1); return false;">1</a></li>`;
        if (startPage > 2) {
            paginationHTML += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        paginationHTML += `
            <li class="page-item ${i === currentPageNum ? 'active' : ''}">
                <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
            </li>
        `;
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            paginationHTML += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
        paginationHTML += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${totalPages}); return false;">${totalPages}</a></li>`;
    }
    
    // Next button
    paginationHTML += `
        <li class="page-item ${currentPageNum === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPageNum + 1}); return false;">Next</a>
        </li>
    `;
    
    pagination.innerHTML = paginationHTML;
}

// Make AJAX request
function makeRequest(url, callback) {
    if (isLoading) return;
    
    showLoading();
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                callback(data.data);
            } else {
                console.error('Request failed:', data.message);
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Request error:', error);
            alert('An error occurred while loading products');
        });
}

// Load all products
function loadAllProducts(page = 1) {
    currentPage = page;
    currentFilter = null;
    currentQuery = null;
    
    const url = `product_actions.php?action=get_all_products&page=${page}`;
    makeRequest(url, (data) => {
        updateProductsContainer(data.products);
        updatePagination(data.totalPages, data.currentPage);
    });
}

// Perform search
function performSearch(page = 1) {
    const searchInput = document.getElementById('searchInput');
    const query = searchInput.value.trim();
    
    if (!query) {
        loadAllProducts(page);
        return;
    }
    
    currentPage = page;
    currentQuery = query;
    currentFilter = null;
    
    const url = `product_actions.php?action=search_products&query=${encodeURIComponent(query)}&page=${page}`;
    makeRequest(url, (data) => {
        updateProductsContainer(data.products);
        updatePagination(data.totalPages, data.currentPage);
    });
}

// Filter by category
function filterByCategory(categoryId, page = 1) {
    currentPage = page;
    currentFilter = { type: 'category', id: categoryId };
    currentQuery = null;
    
    const url = `product_actions.php?action=filter_by_category&category_id=${categoryId}&page=${page}`;
    makeRequest(url, (data) => {
        updateProductsContainer(data.products);
        updatePagination(data.totalPages, data.currentPage);
    });
}

// Filter by brand
function filterByBrand(brandId, page = 1) {
    currentPage = page;
    currentFilter = { type: 'brand', id: brandId };
    currentQuery = null;
    
    const url = `product_actions.php?action=filter_by_brand&brand_id=${brandId}&page=${page}`;
    makeRequest(url, (data) => {
        updateProductsContainer(data.products);
        updatePagination(data.totalPages, data.currentPage);
    });
}

// Reset filters
function resetFilters() {
    currentPage = 1;
    currentFilter = null;
    currentQuery = null;
    
    // Clear search input
    const searchInput = document.getElementById('searchInput');
    if (searchInput) searchInput.value = '';
    
    loadAllProducts(1);
}

// Change page
function changePage(page) {
    if (page < 1 || isLoading) return;
    
    if (currentQuery) {
        performSearch(page);
    } else if (currentFilter) {
        if (currentFilter.type === 'category') {
            filterByCategory(currentFilter.id, page);
        } else if (currentFilter.type === 'brand') {
            filterByBrand(currentFilter.id, page);
        }
    } else {
        loadAllProducts(page);
    }
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Add to cart
function addToCart(productId) {
    // Show loading state
    const button = event.target.closest('.add-to-cart-btn');
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="bi bi-hourglass-split"></i> Adding...';
    button.disabled = true;
    
    // Make API call to add to cart
    fetch('cart_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=add&product_id=${productId}&quantity=1`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.innerHTML = '<i class="bi bi-check-circle"></i> Added!';
            
            // Update cart count if badge exists
            const cartBadge = document.getElementById('cartCount');
            if (cartBadge) {
                cartBadge.textContent = data.cart_count;
                cartBadge.style.display = '';
            }
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.disabled = false;
            }, 1500);
            
            showToast('Product added to cart!', 'success');
        } else {
            button.innerHTML = originalHTML;
            button.disabled = false;
            showToast(data.message || 'Failed to add to cart', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        button.innerHTML = originalHTML;
        button.disabled = false;
        showToast('Error adding to cart. Please try again.', 'danger');
    });
}

// Show toast notification
function showToast(message, type = 'info') {
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="bi bi-${type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, 3000);
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Set up search input event listener
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch();
            }
        });
    }
    
    // Add smooth scrolling for pagination
    document.addEventListener('click', function(e) {
        if (e.target.closest('.page-link')) {
            setTimeout(() => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }, 100);
        }
    });
});