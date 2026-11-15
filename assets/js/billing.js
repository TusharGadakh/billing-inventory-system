/**
 * Interactive Billing POS System Logic
 * Manages dynamic line items, real-time stock lookup, and tax/discount calculations
 */

document.addEventListener('DOMContentLoaded', () => {
    const itemsTableBody = document.querySelector('#itemsTable tbody');
    const addItemBtn = document.getElementById('addItemBtn');
    const subtotalEl = document.getElementById('displaySubtotal');
    const taxAmountEl = document.getElementById('displayTaxAmount');
    const grandTotalEl = document.getElementById('displayGrandTotal');
    const taxRateInput = document.getElementById('taxRate');
    const discountInput = document.getElementById('discountAmount');
    const billingForm = document.getElementById('billingForm');

    let rowCount = 0;

    // Add new line item row
    function addRow() {
        rowCount++;
        const row = document.createElement('tr');
        row.id = `row-${rowCount}`;
        row.innerHTML = `
            <td>
                <select name="items[${rowCount}][product_id]" class="form-control product-select" required>
                    <option value="">-- Select Product --</option>
                    ${window.availableProducts ? window.availableProducts.map(p => 
                        `<option value="${p.id}" data-price="${p.selling_price}" data-stock="${p.current_stock}" data-unit="${p.unit}">
                            ${p.name} (${p.sku}) - Stock: ${p.current_stock} ${p.unit}
                        </option>`
                    ).join('') : ''}
                </select>
                <div class="stock-warning text-danger" style="display:none; font-size:11px; margin-top:4px;"></div>
            </td>
            <td>
                <input type="number" name="items[${rowCount}][quantity]" class="form-control item-qty" value="1" min="1" required style="width: 90px;">
            </td>
            <td>
                <div style="display: flex; align-items: center; gap: 4px;">
                    <span>$</span>
                    <input type="number" step="0.01" name="items[${rowCount}][price]" class="form-control item-price" value="0.00" readonly style="width: 100px;">
                </div>
            </td>
            <td>
                <div style="font-weight: 600; font-size: 14px; padding-top: 8px;">
                    $<span class="item-total">0.00</span>
                </div>
            </td>
            <td style="text-align: center;">
                <button type="button" class="btn-action delete remove-row-btn" title="Remove Item">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </td>
        `;

        itemsTableBody.appendChild(row);
        attachRowEvents(row);
        calculateTotals();
    }

    // Attach listeners to a row
    function attachRowEvents(row) {
        const select = row.querySelector('.product-select');
        const qtyInput = row.querySelector('.item-qty');
        const priceInput = row.querySelector('.item-price');
        const totalSpan = row.querySelector('.item-total');
        const warningDiv = row.querySelector('.stock-warning');
        const removeBtn = row.querySelector('.remove-row-btn');

        // When product selection changes
        select.addEventListener('change', async function () {
            const prodId = this.value;
            if (!prodId) {
                priceInput.value = '0.00';
                qtyInput.max = 9999;
                warningDiv.style.display = 'none';
                calculateRowTotal();
                return;
            }

            try {
                const response = await fetch(`api/get_product.php?id=${prodId}`);
                const data = await response.json();

                if (data.success) {
                    const product = data.product;
                    priceInput.value = product.selling_price.toFixed(2);
                    qtyInput.max = product.current_stock;

                    if (product.current_stock <= 0) {
                        warningDiv.textContent = 'Out of Stock! Cannot bill.';
                        warningDiv.style.display = 'block';
                        qtyInput.disabled = true;
                    } else {
                        qtyInput.disabled = false;
                        if (parseInt(qtyInput.value) > product.current_stock) {
                            qtyInput.value = product.current_stock;
                        }
                        warningDiv.style.display = 'none';
                    }
                    calculateRowTotal();
                } else {
                    alert(data.message || 'Product lookup error.');
                }
            } catch (err) {
                console.error('Failed to load product details', err);
            }
        });

        // Quantity input changes
        qtyInput.addEventListener('input', function () {
            const maxStock = parseInt(this.max) || 0;
            const currentVal = parseInt(this.value) || 0;

            if (maxStock > 0 && currentVal > maxStock) {
                warningDiv.textContent = `Only ${maxStock} available in stock!`;
                warningDiv.style.display = 'block';
                this.value = maxStock;
            } else {
                warningDiv.style.display = 'none';
            }
            calculateRowTotal();
        });

        // Calculate single row total
        function calculateRowTotal() {
            const qty = parseFloat(qtyInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const rowTotal = qty * price;
            totalSpan.textContent = rowTotal.toFixed(2);
            calculateTotals();
        }

        // Remove row
        removeBtn.addEventListener('click', function () {
            if (itemsTableBody.querySelectorAll('tr').length > 1) {
                row.remove();
                calculateTotals();
            } else {
                alert('An invoice must contain at least one item.');
            }
        });
    }

    // Calculate entire bill totals
    function calculateTotals() {
        let subtotal = 0;
        const rows = itemsTableBody.querySelectorAll('tr');

        rows.forEach(row => {
            const totalText = row.querySelector('.item-total');
            if (totalText) {
                subtotal += parseFloat(totalText.textContent) || 0;
            }
        });

        const taxRate = parseFloat(taxRateInput ? taxRateInput.value : 0) || 0;
        const discount = parseFloat(discountInput ? discountInput.value : 0) || 0;

        const taxableBase = Math.max(0, subtotal - discount);
        const taxAmount = (taxableBase * taxRate) / 100;
        const grandTotal = taxableBase + taxAmount;

        if (subtotalEl) subtotalEl.textContent = subtotal.toFixed(2);
        if (taxAmountEl) taxAmountEl.textContent = taxAmount.toFixed(2);
        if (grandTotalEl) grandTotalEl.textContent = grandTotal.toFixed(2);

        // Update hidden fields if any
        const hiddenSubtotal = document.getElementById('hiddenSubtotal');
        const hiddenTaxAmount = document.getElementById('hiddenTaxAmount');
        const hiddenGrandTotal = document.getElementById('hiddenGrandTotal');

        if (hiddenSubtotal) hiddenSubtotal.value = subtotal.toFixed(2);
        if (hiddenTaxAmount) hiddenTaxAmount.value = taxAmount.toFixed(2);
        if (hiddenGrandTotal) hiddenGrandTotal.value = grandTotal.toFixed(2);
    }

    // Attach global tax and discount listeners
    if (taxRateInput) taxRateInput.addEventListener('input', calculateTotals);
    if (discountInput) discountInput.addEventListener('input', calculateTotals);

    // Add first row on initial load
    if (addItemBtn) {
        addItemBtn.addEventListener('click', addRow);
        // Start with 1 row automatically
        addRow();
    }

    // Pre-submission validation
    if (billingForm) {
        billingForm.addEventListener('submit', function (e) {
            const rows = itemsTableBody.querySelectorAll('tr');
            let hasValidItem = false;

            for (const row of rows) {
                const select = row.querySelector('.product-select');
                const qtyInput = row.querySelector('.item-qty');
                if (select && select.value && parseInt(qtyInput.value) > 0) {
                    hasValidItem = true;
                    break;
                }
            }

            if (!hasValidItem) {
                e.preventDefault();
                alert('Please select at least one valid product with quantity > 0 before creating the invoice.');
            }
        });
    }
});
