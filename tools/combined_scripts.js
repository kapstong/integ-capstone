
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        function toggleSidebarDesktop() {
            const sidebar = document.getElementById('sidebar');
            const content = document.querySelector('.content');
            const arrow = document.getElementById('sidebarArrow');
            const toggle = document.querySelector('.sidebar-toggle');
            const logoImg = document.querySelector('.navbar-brand img');
            sidebar.classList.toggle('sidebar-collapsed');
            const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
            if (isCollapsed) {
                logoImg.src = 'atieralogo2.png';
                content.style.marginLeft = '120px';
                arrow.classList.remove('fa-chevron-left');
                arrow.classList.add('fa-chevron-right');
                toggle.style.left = '110px';
            } else {
                logoImg.src = 'atieralogo.png';
                content.style.marginLeft = '300px';
                arrow.classList.remove('fa-chevron-right');
                arrow.classList.add('fa-chevron-left');
                toggle.style.left = '290px';
            }
        }


        // Global variables
        let currentIncomeStatementData = null;
        let currentBalanceSheetData = null;
        let currentCashFlowData = null;

        // Initialize sidebar state on page load
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const content = document.querySelector('.content');
            const arrow = document.getElementById('sidebarArrow');
            const toggle = document.querySelector('.sidebar-toggle');
            const logoImg = document.querySelector('.navbar-brand img');
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('sidebar-collapsed');
                logoImg.src = 'atieralogo2.png';
                content.style.marginLeft = '120px';
                arrow.classList.remove('fa-chevron-left');
                arrow.classList.add('fa-chevron-right');
                toggle.style.left = '110px';
            } else {
                sidebar.classList.remove('sidebar-collapsed');
                logoImg.src = 'atieralogo.png';
                content.style.marginLeft = '300px';
                arrow.classList.remove('fa-chevron-right');
                arrow.classList.add('fa-chevron-left');
                toggle.style.left = '290px';
            }

            // Load initial income statement
            generateIncomeStatement();

            // Auto-generate reports when tabs are shown
            const balanceTab = document.getElementById('balance-tab');
            const cashflowTab = document.getElementById('cashflow-tab');

            let balanceGenerated = false;
            let cashflowGenerated = false;

            balanceTab.addEventListener('shown.bs.tab', function() {
                if (!balanceGenerated) {
                    generateBalanceSheet();
                    balanceGenerated = true;
                }
            });

            cashflowTab.addEventListener('shown.bs.tab', function() {
                if (!cashflowGenerated) {
                    generateCashFlow();
                    cashflowGenerated = true;
                }
            });
        });

        // Update income statement period selector
        function updateIncomeStatementPeriod() {
            const periodSelect = document.getElementById('incomePeriodSelect');
            const customRange = document.getElementById('incomeCustomRange');

            if (periodSelect.value === 'custom') {
                customRange.style.display = 'block';
            } else {
                customRange.style.display = 'none';
            }
        }

        // Generate income statement
        async function generateIncomeStatement() {
            const container = document.getElementById('incomeStatementContainer');
            const periodElement = document.getElementById('incomeStatementPeriod');

            // Show loading state
            container.innerHTML = `
                <div class="statement-header">
                    <h1 class="statement-title">Profit & Loss Statement</h1>
                    <p class="statement-period">Loading...</p>
                </div>
                <div class="text-center py-5">
                    <div class="loading mb-3"></div>
                    <p class="text-muted">Generating income statement...</p>
                </div>
            `;

            let data = null;
            try {
                // Get date range based on selection
                const periodSelect = document.getElementById('incomePeriodSelect');
                let dateFrom, dateTo;

                if (periodSelect.value === 'custom') {
                    dateFrom = document.getElementById('incomeFromDate').value;
                    dateTo = document.getElementById('incomeToDate').value;
                } else {
                    const dates = getDateRange(periodSelect.value);
                    dateFrom = dates.from;
                    dateTo = dates.to;
                }

                // Fetch income statement data
                const response = await fetch(`../api/reports.php?type=income_statement&date_from=${dateFrom}&date_to=${dateTo}`);

                // Get the response text first (even if status is error, API returns JSON with details)
                const responseText = await response.text();
                if (!responseText) {
                    throw new Error('Empty response from server');
                }

                // Parse JSON
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('Response text:', responseText);
                    throw new Error('Invalid JSON response from server. Response: ' + responseText.substring(0, 500));
                }

                // Check for errors in the response
                if (!response.ok) {
                    throw new Error(data.error || `HTTP error! status: ${response.status}`);
                }

                if (!data.success || data.error) {
                    throw new Error(data.error || 'Failed to generate income statement');
                }

                // Store data globally for export
                currentIncomeStatementData = data;

                // Render the income statement
                renderIncomeStatement(data);

            } catch (error) {
                console.error('Error generating income statement:', error);
                console.error('Response data:', data);

                // Build detailed error message
                let errorHTML = `Error generating income statement: ${error.message}`;
                if (data && data.file) {
                    errorHTML += `<br><small><strong>File:</strong> ${data.file}:${data.line}</small>`;
                }
                if (data && data.trace) {
                    errorHTML += `<br><details><summary>Stack trace</summary><pre style="text-align: left; font-size: 11px; max-height: 200px; overflow: auto; background: #f5f5f5; padding: 10px; margin-top: 10px;">${data.trace}</pre></details>`;
                }

                container.innerHTML = `
                    <div class="statement-header">
                        <h1 class="statement-title">Profit & Loss Statement</h1>
                        <p class="statement-period">Error loading report</p>
                    </div>
                    <div class="text-center py-5">
                        <div class="alert alert-danger" style="text-align: left;">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${errorHTML}
                        </div>
                        <button class="btn btn-primary" onclick="generateIncomeStatement()">Try Again</button>
                    </div>
                `;
            }
        }

        // Render income statement
        function renderIncomeStatement(data) {
            const container = document.getElementById('incomeStatementContainer');

            // Build HTML content
            let html = `
                <div class="statement-header">
                    <h1 class="statement-title">Profit & Loss Statement</h1>
                    <p class="statement-period">For the period ${formatDate(data.date_from)} to ${formatDate(data.date_to)}</p>
                </div>

                <div class="account-category">
                    <h6>Revenue</h6>
            `;

            // Add revenue accounts
            if (data.revenue.accounts && data.revenue.accounts.length > 0) {
                data.revenue.accounts.forEach(account => {
                    html += `
                        <div class="account-item">
                            <span class="account-name">${account.account_name}</span>
                            <span class="account-amount">₱${parseFloat(account.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        </div>
                    `;
                });
            }

            html += `
                    <div class="account-item total-row">
                        <span class="account-name"><strong>Total Revenue</strong></span>
                        <span class="account-amount"><strong>₱${parseFloat(data.revenue.total || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                    </div>
                </div>

                <div class="account-category">
                    <h6>Cost of Goods Sold</h6>
                    <div class="account-item total-row">
                        <span class="account-name"><strong>Total COGS</strong></span>
                        <span class="account-amount"><strong>₱0.00</strong></span>
                    </div>
                </div>

                <div class="account-category">
                    <h6>Gross Profit</h6>
                    <div class="account-item total-row">
                        <span class="account-name"><strong>Gross Profit</strong></span>
                        <span class="account-amount positive-amount"><strong>₱${parseFloat(data.revenue.total || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                    </div>
                </div>

                <div class="account-category">
                    <h6>Operating Expenses</h6>
            `;

            // Add expense accounts
            if (data.expenses.accounts && data.expenses.accounts.length > 0) {
                data.expenses.accounts.forEach(account => {
                    html += `
                        <div class="account-item">
                            <span class="account-name">${account.account_name}</span>
                            <span class="account-amount">₱${parseFloat(account.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        </div>
                    `;
                });
            }

            html += `
                    <div class="account-item total-row">
                        <span class="account-name"><strong>Total Operating Expenses</strong></span>
                        <span class="account-amount"><strong>₱${parseFloat(data.expenses.total || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                    </div>
                </div>

                <div class="account-category">
                    <h6>Net Profit</h6>
                    <div class="account-item total-row">
                        <span class="account-name"><strong>Net Profit</strong></span>
                        <span class="account-amount ${parseFloat(data.net_profit || 0) >= 0 ? 'positive-amount' : 'negative-amount'}"><strong>₱${parseFloat(data.net_profit || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                    </div>
                </div>
            `;

            container.innerHTML = html;

            // Apply privacy masking immediately to prevent flash
            setTimeout(function() {
                if (typeof PrivacyMode !== 'undefined') {
                    PrivacyMode.hide();
                }
            }, 10);
        }

        // Export income statement
        function exportIncomeStatement(format) {
            if (!currentIncomeStatementData) {
                showAlert('Please generate the report first', 'warning');
                return;
            }

            // Create CSV content
            let csvContent = 'data:text/csv;charset=utf-8,';
            csvContent += 'Profit & Loss Statement\n';
            csvContent += `Period: ${currentIncomeStatementData.date_from} to ${currentIncomeStatementData.date_to}\n\n`;

            csvContent += 'Revenue\n';
            csvContent += 'Account,Amount\n';
            if (currentIncomeStatementData.revenue.accounts) {
                currentIncomeStatementData.revenue.accounts.forEach(account => {
                    csvContent += `"${account.account_name}","${account.amount}"\n`;
                });
            }
            csvContent += `"Total Revenue","${currentIncomeStatementData.revenue.total}"\n\n`;

            csvContent += 'Expenses\n';
            csvContent += 'Account,Amount\n';
            if (currentIncomeStatementData.expenses.accounts) {
                currentIncomeStatementData.expenses.accounts.forEach(account => {
                    csvContent += `"${account.account_name}","${account.amount}"\n`;
                });
            }
            csvContent += `"Total Expenses","${currentIncomeStatementData.expenses.total}"\n\n`;

            csvContent += `"Net Profit","${currentIncomeStatementData.net_profit}"\n`;

            // Download CSV
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', `income_statement_${currentIncomeStatementData.date_from}_to_${currentIncomeStatementData.date_to}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            showAlert('Income statement exported successfully', 'success');
        }

        // Get date range based on period
        function getDateRange(period) {
            const now = new Date();
            let from, to;

            switch (period) {
                case 'current_month':
                    from = new Date(now.getFullYear(), now.getMonth(), 1);
                    to = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                    break;
                case 'last_month':
                    from = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                    to = new Date(now.getFullYear(), now.getMonth(), 0);
                    break;
                case 'last_quarter':
                    const quarterStart = new Date(now.getFullYear(), Math.floor(now.getMonth() / 3) * 3 - 3, 1);
                    const quarterEnd = new Date(now.getFullYear(), Math.floor(now.getMonth() / 3) * 3, 0);
                    from = quarterStart;
                    to = quarterEnd;
                    break;
                case 'year_to_date':
                    from = new Date(now.getFullYear(), 0, 1);
                    to = now;
                    break;
                default:
                    from = new Date(now.getFullYear(), now.getMonth(), 1);
                    to = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            }

            return {
                from: from.toISOString().split('T')[0],
                to: to.toISOString().split('T')[0]
            };
        }

        // Format date helper function
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        // Update balance sheet when period changes
        function updateBalanceSheet() {
            if (typeof generateBalanceSheet === 'function') {
                generateBalanceSheet();
            }
        }

        // Generate balance sheet
        async function generateBalanceSheet() {
            const container = document.getElementById('balanceSheetContainer');
            const dateSelect = document.getElementById('balanceDateSelect');
            const asOfDate = dateSelect ? dateSelect.value : 'current';

            // Show loading state
            container.innerHTML = `
                <div class="statement-header">
                    <h1 class="statement-title">Balance Sheet</h1>
                    <p class="statement-period">Loading...</p>
                </div>
                <div class="text-center py-5">
                    <div class="loading mb-3"></div>
                    <p class="text-muted">Generating balance sheet...</p>
                </div>
            `;

            try {
                // Fetch balance sheet data
                const response = await fetch(`../api/reports.php?type=balance_sheet&as_of_date=${asOfDate}`);

                // Check if response is OK
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                // Get the response text first to check if it's empty
                const responseText = await response.text();
                if (!responseText) {
                    throw new Error('Empty response from server');
                }

                // Parse JSON
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('Response text:', responseText);
                    throw new Error('Invalid JSON response from server');
                }

                if (!data.success || data.error) {
                    throw new Error(data.error || 'Failed to generate balance sheet');
                }

                // Store data globally for export
                currentBalanceSheetData = data;

                // Render the balance sheet
                renderBalanceSheet(data);

            } catch (error) {
                console.error('Error generating balance sheet:', error);
                container.innerHTML = `
                    <div class="statement-header">
                        <h1 class="statement-title">Balance Sheet</h1>
                        <p class="statement-period">Error loading report</p>
                    </div>
                    <div class="text-center py-5">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error generating balance sheet: ${error.message}
                        </div>
                        <button class="btn btn-primary" onclick="generateBalanceSheet()">Try Again</button>
                    </div>
                `;
            }
        }

        // Render balance sheet
        function renderBalanceSheet(data) {
            const container = document.getElementById('balanceSheetContainer');

            let assetsHtml = '<div class="account-category"><h6>Assets</h6>';
            if (data.assets.accounts && data.assets.accounts.length > 0) {
                data.assets.accounts.forEach(account => {
                    assetsHtml += `
                        <div class="account-item">
                            <span class="account-name">${account.account_name}</span>
                            <span class="account-amount">₱${parseFloat(account.account_balance || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        </div>
                    `;
                });
            }
            assetsHtml += `
                <div class="account-item total-row">
                    <span class="account-name"><strong>Total Assets</strong></span>
                    <span class="account-amount"><strong>₱${parseFloat(data.assets.total || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                </div>
            </div>`;

            let liabilitiesHtml = '<div class="account-category"><h6>Liabilities & Equity</h6>';
            if (data.liabilities.accounts && data.liabilities.accounts.length > 0) {
                data.liabilities.accounts.forEach(account => {
                    liabilitiesHtml += `
                        <div class="account-item">
                            <span class="account-name">${account.account_name}</span>
                            <span class="account-amount">₱${parseFloat(account.account_balance || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        </div>
                    `;
                });
            }

            if (data.equity.accounts && data.equity.accounts.length > 0) {
                data.equity.accounts.forEach(account => {
                    liabilitiesHtml += `
                        <div class="account-item">
                            <span class="account-name">${account.account_name}</span>
                            <span class="account-amount">₱${parseFloat(account.account_balance || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        </div>
                    `;
                });
            }

            liabilitiesHtml += `
                <div class="account-item total-row">
                    <span class="account-name"><strong>Total Liabilities & Equity</strong></span>
                    <span class="account-amount"><strong>₱${parseFloat((data.liabilities.total || 0) + (data.equity.total || 0)).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                </div>
            </div>`;

            container.innerHTML = `
                <div class="statement-header">
                    <h1 class="statement-title">Balance Sheet</h1>
                    <p class="statement-period">As of ${formatDate(data.as_of_date)}</p>
                </div>
                <div class="row">
                    <div class="col-md-6">${assetsHtml}</div>
                    <div class="col-md-6">${liabilitiesHtml}</div>
                </div>
            `;

            // Apply privacy masking immediately to prevent flash
            setTimeout(function() {
                if (typeof PrivacyMode !== 'undefined') {
                    PrivacyMode.hide();
                }
            }, 10);
        }

        // Generate cash flow statement
        async function generateCashFlow() {
            const container = document.getElementById('cashFlowContainer');
            const periodSelect = document.getElementById('cashFlowPeriodSelect');
            const period = periodSelect ? periodSelect.value : 'last_quarter';

            // Show loading state
            container.innerHTML = `
                <div class="statement-header">
                    <h1 class="statement-title">Cash Flow Statement</h1>
                    <p class="statement-period">Loading...</p>
                </div>
                <div class="text-center py-5">
                    <div class="loading mb-3"></div>
                    <p class="text-muted">Generating cash flow statement...</p>
                </div>
            `;

            try {
                // Fetch cash flow data
                const response = await fetch(`../api/reports.php?type=cash_flow&period=${period}`);

                // Check if response is OK
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                // Get the response text first to check if it's empty
                const responseText = await response.text();
                if (!responseText) {
                    throw new Error('Empty response from server');
                }

                // Parse JSON
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('Response text:', responseText);
                    throw new Error('Invalid JSON response from server');
                }

                if (!data.success || data.error) {
                    throw new Error(data.error || 'Failed to generate cash flow statement');
                }

                // Check if cash_flow data exists
                if (!data.cash_flow) {
                    throw new Error('Invalid response format: missing cash_flow data');
                }

                // Store data globally for export
                currentCashFlowData = data;

                // Render the cash flow statement
                renderCashFlow(data);

            } catch (error) {
                console.error('Error generating cash flow:', error);
                container.innerHTML = `
                    <div class="statement-header">
                        <h1 class="statement-title">Cash Flow Statement</h1>
                        <p class="statement-period">Error loading report</p>
                    </div>
                    <div class="text-center py-5">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error: ${error.message}
                        </div>
                        <button class="btn btn-primary" onclick="generateCashFlow()">Try Again</button>
                    </div>
                `;
            }
        }

        // Render cash flow statement
        function renderCashFlow(data) {
            const container = document.getElementById('cashFlowContainer');

            // Check if we have cash_flow data
            if (!data.cash_flow) {
                container.innerHTML = `
                    <div class="text-center py-5">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error: Invalid cash flow data format. Missing cash flow information.
                        </div>
                    </div>
                `;
                return;
            }

            const operating = data.cash_flow.operating_activities;
            const investing = data.cash_flow.investing_activities;
            const financing = data.cash_flow.financing_activities;

            // Default values if data is missing
            const operatingAmount = operating?.amount || 0;
            const investingAmount = investing?.amount || 0;
            const financingAmount = financing?.amount || 0;
            const netCashFlow = operatingAmount + investingAmount + financingAmount;

            // Check if all activities have the expected structure
            if (!operating || !investing || !financing ||
                typeof operating.amount !== 'number' ||
                typeof investing.amount !== 'number' ||
                typeof financing.amount !== 'number') {
                container.innerHTML = `
                    <div class="text-center py-5">
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle me-2"></i>
                            Cash flow data is incomplete. Some activities may not be available.
                        </div>
                    <div class="text-center py-3">
                        <div class="alert alert-info">
                            Net Cash Flow: ₱${parseFloat(netCashFlow).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                        </div>
                    </div>
                `;
                return; // Exit early for incomplete data
            }

            let html = `
                <div class="statement-header">
                    <h1 class="statement-title">Cash Flow Statement</h1>
                    <p class="statement-period">For the period ${formatDate(data.start_date)} to ${formatDate(data.end_date)}</p>
                </div>

                <div class="account-category">
                    <h6>Operating Activities</h6>
                    <div class="ps-3">
                        <div class="fw-bold text-success mb-2">Cash Inflows (Revenue):</div>
            `;

            // Revenue breakdown
            if (operating.revenue && operating.revenue.length > 0) {
                operating.revenue.forEach(rev => {
                    html += `
                        <div class="account-item ps-3">
                            <span class="account-name">${rev.name}</span>
                            <span class="account-amount text-success">₱${parseFloat(rev.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        </div>
                    `;
                });
            } else {
                html += `
                    <div class="account-item ps-3">
                        <span class="account-name text-muted">No revenue recorded</span>
                        <span class="account-amount">₱0.00</span>
                    </div>
                `;
            }

            html += `
                        <div class="account-item ps-3 border-bottom pb-2">
                            <span class="account-name"><strong>Total Revenue</strong></span>
                            <span class="account-amount text-success"><strong>₱${parseFloat(operating.total_revenue || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                        </div>

                        <div class="fw-bold text-danger mb-2 mt-3">Cash Outflows (Operating Expenses):</div>
            `;

            // Expense category breakdown
            if (operating.expenses_by_category && operating.expenses_by_category.length > 0) {
                operating.expenses_by_category.forEach(expense => {
                    const sourceBadges = expense.sources ? expense.sources.split(',').map(src => {
                        const badgeClass = src === 'HR_SYSTEM' ? 'bg-primary' : src === 'LOGISTICS1' ? 'bg-warning' : src === 'LOGISTICS2' ? 'bg-info' : 'bg-secondary';
                        return `<span class="badge ${badgeClass} text-xs">${src}</span>`;
                    }).join(' ') : '';

                    html += `
                        <div class="account-item ps-3">
                            <span class="account-name">
                                ${expense.name}
                                ${sourceBadges}
                                <button class="btn btn-sm btn-link text-decoration-none p-0 ms-2"
                                        onclick="toggleExpenseDetail('${expense.subcategory}')"
                                        title="View detailed breakdown">
                                    <i class="fas fa-chevron-down" id="icon-${expense.subcategory}"></i>
                                </button>
                            </span>
                            <span class="account-amount text-danger">₱${parseFloat(expense.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        </div>
                        <div id="detail-${expense.subcategory}" class="ps-5 d-none" style="background-color: #f8f9fa; margin: 5px 0; padding: 10px; border-radius: 4px;">
                    `;

                    // Show detailed breakdown for this category
                    if (operating.expense_details && operating.expense_details.length > 0) {
                        const categoryDetails = operating.expense_details.filter(d => d.expense_category === expense.subcategory);
                        if (categoryDetails.length > 0) {
                            categoryDetails.forEach(detail => {
                                const sourceLabel = detail.source_system === 'HR_SYSTEM' ? 'HR4 Payroll' :
                                                  detail.source_system === 'LOGISTICS1' ? 'Logistics 1' :
                                                  detail.source_system === 'LOGISTICS2' ? 'Logistics 2' : detail.source_system;
                                html += `
                                    <div class="account-item" style="font-size: 0.9em;">
                                        <span class="account-name text-muted">
                                            ${detail.department || 'General'}
                                            <span class="badge bg-light text-dark">${sourceLabel}</span>
                                            <small>(${detail.transaction_count} transactions)</small>
                                        </span>
                                        <span class="account-amount">₱${parseFloat(detail.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                    </div>
                                `;
                            });
                        }
                    }

                    html += `</div>`;
                });
            } else {
                html += `
                    <div class="account-item ps-3">
                        <span class="account-name text-muted">No operating expenses recorded</span>
                        <span class="account-amount">₱0.00</span>
                    </div>
                `;
            }

            html += `
                        <div class="account-item ps-3 border-bottom pb-2">
                            <span class="account-name"><strong>Total Operating Expenses</strong></span>
                            <span class="account-amount text-danger"><strong>₱${parseFloat(operating.total_expenses || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                        </div>
                    </div>

                    <div class="account-item total-row mt-2">
                        <span class="account-name"><strong>Net Cash from Operating Activities</strong></span>
                        <span class="account-amount ${operating.amount >= 0 ? 'text-success' : 'text-danger'}"><strong>₱${parseFloat(operating.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                    </div>
                </div>

                <div class="account-category">
                    <h6>Investing Activities</h6>
            `;

            if (investing.accounts && investing.accounts.length > 0) {
                investing.accounts.forEach(account => {
                    html += `
                        <div class="account-item">
                            <span class="account-name">${account.account_name}</span>
                            <span class="account-amount">₱${parseFloat(account.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        </div>
                    `;
                });
            } else {
                html += `
                    <div class="account-item">
                        <span class="account-name">No investing activities</span>
                        <span class="account-amount">₱0.00</span>
                    </div>
                `;
            }

            html += `
                    <div class="account-item total-row">
                        <span class="account-name"><strong>Net Cash from Investing Activities</strong></span>
                        <span class="account-amount ${investing.amount < 0 ? 'negative-amount' : ''}"><strong>₱${parseFloat(investing.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                    </div>
                </div>

                <div class="account-category">
                    <h6>Financing Activities</h6>
            `;

            if (financing.accounts && financing.accounts.length > 0) {
                financing.accounts.forEach(account => {
                    html += `
                        <div class="account-item">
                            <span class="account-name">${account.account_name}</span>
                            <span class="account-amount">₱${parseFloat(account.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        </div>
                    `;
                });
            } else {
                html += `
                    <div class="account-item">
                        <span class="account-name">No financing activities</span>
                        <span class="account-amount">₱0.00</span>
                    </div>
                `;
            }

            html += `
                    <div class="account-item total-row">
                        <span class="account-name"><strong>Net Cash from Financing Activities</strong></span>
                        <span class="account-amount"><strong>₱${parseFloat(financing.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                    </div>
                </div>

                <div class="account-category">
                    <h6>Net Cash Flow & Change in Cash</h6>
                    <div class="account-item total-row">
                        <span class="account-name"><strong>Net Increase/(Decrease) in Cash</strong></span>
                        <span class="account-amount ${netCashFlow >= 0 ? 'positive-amount' : 'negative-amount'}"><strong>₱${parseFloat(netCashFlow).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></span>
                    </div>
                </div>
            `;

            container.innerHTML = html;

            // Apply privacy masking immediately to prevent flash
            setTimeout(function() {
                if (typeof PrivacyMode !== 'undefined') {
                    PrivacyMode.hide();
                }
            }, 10);
        }

        // Generate budget report
        async function generateBudgetReport() {
            const container = document.getElementById('budgetVsActualContainer');

            // Show loading state
            container.innerHTML = `
                <div class="text-center py-3">
                    <div class="loading mb-3"></div>
                    <p class="text-muted">Generating budget report...</p>
                </div>
            `;

            try {
                const response = await fetch('../api/reports.php?type=budget_vs_actual');

                // Check if response is OK
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                // Get the response text first to check if it's empty
                const responseText = await response.text();
                if (!responseText) {
                    throw new Error('Empty response from server');
                }

                // Parse JSON
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('Response text:', responseText);
                    throw new Error('Invalid JSON response from server');
                }

                if (data.error) {
                    throw new Error(data.error);
                }

                // For now, just show a message that budget reporting is not implemented
                container.innerHTML = `
                    <div class="text-center py-3">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Budget vs Actual reporting requires budget data setup first.
                        </div>
                    </div>
                `;

            } catch (error) {
                console.error('Error generating budget report:', error);
                container.innerHTML = `
                    <div class="text-center py-3">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error generating budget report: ${error.message}
                        </div>
                    </div>
                `;
            }
        }

        // Generate cash flow summary
        async function generateCashFlowSummary() {
            const container = document.getElementById('cashFlowSummaryContainer');

            // Show loading state
            container.innerHTML = `
                <div class="text-center py-3">
                    <div class="loading mb-3"></div>
                    <p class="text-muted">Generating cash flow summary...</p>
                </div>
            `;

            try {
                const response = await fetch('../api/reports.php?type=cash_flow_summary');

                // Check if response is OK
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                // Get the response text first to check if it's empty
                const responseText = await response.text();
                if (!responseText) {
                    throw new Error('Empty response from server');
                }

                // Parse JSON
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('Response text:', responseText);
                    throw new Error('Invalid JSON response from server');
                }

                if (!data.success || data.error) {
                    throw new Error(data.error || 'Failed to generate cash flow summary');
                }

                const summary = data.summary;
                container.innerHTML = `
                    <div class="d-flex justify-content-between">
                        <span>Payroll Expenses (30 days)</span>
                        <span class="text-danger">-₱${parseFloat(summary.payroll_expenses || 0).toLocaleString()}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Operating Expenses (30 days)</span>
                        <span class="text-danger">-₱${parseFloat(summary.operating_expenses || 0).toLocaleString()}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Days Monitored</span>
                        <span>${summary.days_counted || 0} days</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span><strong>Total Cash Outflow</strong></span>
                        <span class="text-danger"><strong>-₱${parseFloat(summary.total_expenses || 0).toLocaleString()}</strong></span>
                    </div>
                `;

            } catch (error) {
                console.error('Error generating cash flow summary:', error);
                container.innerHTML = `
                    <div class="text-center py-3">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error generating cash flow summary: ${error.message}
                        </div>
                    </div>
                `;
            }
        }

        // Export functions
        function exportBalanceSheet(format) {
            if (!currentBalanceSheetData) {
                showAlert('Please generate the balance sheet first', 'warning');
                return;
            }

            // Create CSV content
            let csvContent = 'data:text/csv;charset=utf-8,';
            csvContent += 'Balance Sheet\n';
            csvContent += `As of: ${currentBalanceSheetData.as_of_date}\n\n`;

            // Assets section
            csvContent += 'ASSETS\n';
            csvContent += 'Account,Amount\n';
            if (currentBalanceSheetData.assets.accounts) {
                currentBalanceSheetData.assets.accounts.forEach(account => {
                    csvContent += `"${account.account_name}","${account.account_balance || 0}"\n`;
                });
            }
            csvContent += `"Total Assets","${currentBalanceSheetData.assets.total}"\n\n`;

            // Liabilities section
            csvContent += 'LIABILITIES\n';
            csvContent += 'Account,Amount\n';
            if (currentBalanceSheetData.liabilities.accounts) {
                currentBalanceSheetData.liabilities.accounts.forEach(account => {
                    csvContent += `"${account.account_name}","${account.account_balance || 0}"\n`;
                });
            }
            csvContent += `"Total Liabilities","${currentBalanceSheetData.liabilities.total}"\n\n`;

            // Equity section
            csvContent += 'EQUITY\n';
            csvContent += 'Account,Amount\n';
            if (currentBalanceSheetData.equity.accounts) {
                currentBalanceSheetData.equity.accounts.forEach(account => {
                    csvContent += `"${account.account_name}","${account.account_balance || 0}"\n`;
                });
            }
            csvContent += `"Total Equity","${currentBalanceSheetData.equity.total}"\n\n`;

            csvContent += `"Total Liabilities & Equity","${currentBalanceSheetData.liabilities.total + currentBalanceSheetData.equity.total}"\n`;

            // Download CSV
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', `balance_sheet_${currentBalanceSheetData.as_of_date}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            showAlert('Balance sheet exported successfully', 'success');
        }

        function exportCashFlow(format) {
            if (!currentCashFlowData) {
                showAlert('Please generate the cash flow statement first', 'warning');
                return;
            }

            // Create CSV content
            let csvContent = 'data:text/csv;charset=utf-8,';
            csvContent += 'Cash Flow Statement\n';
            csvContent += `Period: ${currentCashFlowData.start_date} to ${currentCashFlowData.end_date}\n\n`;

            // Operating Activities
            csvContent += 'OPERATING ACTIVITIES\n\n';
            csvContent += 'Cash Inflows (Revenue)\n';
            csvContent += 'Account,Amount\n';
            const operating = currentCashFlowData.cash_flow.operating_activities;

            if (operating.revenue && operating.revenue.length > 0) {
                operating.revenue.forEach(rev => {
                    csvContent += `"${rev.name}","${rev.amount || 0}"\n`;
                });
            }
            csvContent += `"Total Revenue","${operating.total_revenue || 0}"\n\n`;

            csvContent += 'Cash Outflows (Operating Expenses)\n';
            csvContent += 'Category,Source System,Amount\n';
            if (operating.expenses_by_category && operating.expenses_by_category.length > 0) {
                operating.expenses_by_category.forEach(expense => {
                    csvContent += `"${expense.name}","${expense.sources || 'N/A'}","${expense.amount || 0}"\n`;
                });
            }
            csvContent += `"Total Operating Expenses","","${operating.total_expenses || 0}"\n\n`;

            csvContent += 'Detailed Expense Breakdown by Department\n';
            csvContent += 'Department,Category,Source,Amount,Transactions\n';
            if (operating.expense_details && operating.expense_details.length > 0) {
                operating.expense_details.forEach(detail => {
                    csvContent += `"${detail.department || 'General'}","${detail.expense_category}","${detail.source_system}","${detail.amount || 0}","${detail.transaction_count}"\n`;
                });
            }

            csvContent += `\n"Net Cash from Operating Activities","","","${operating.amount}"\n\n`;

            // Investing Activities
            csvContent += 'INVESTING ACTIVITIES\n';
            csvContent += 'Account,Amount\n';
            if (currentCashFlowData.cash_flow.investing_activities.accounts) {
                currentCashFlowData.cash_flow.investing_activities.accounts.forEach(account => {
                    csvContent += `"${account.account_name}","${account.amount || 0}"\n`;
                });
            }
            csvContent += `"Net Cash from Investing Activities","${currentCashFlowData.cash_flow.investing_activities.amount}"\n\n`;

            // Financing Activities
            csvContent += 'FINANCING ACTIVITIES\n';
            csvContent += 'Account,Amount\n';
            if (currentCashFlowData.cash_flow.financing_activities.accounts) {
                currentCashFlowData.cash_flow.financing_activities.accounts.forEach(account => {
                    csvContent += `"${account.account_name}","${account.amount || 0}"\n`;
                });
            }
            csvContent += `"Net Cash from Financing Activities","${currentCashFlowData.cash_flow.financing_activities.amount}"\n\n`;

            csvContent += `"Net Change in Cash","${currentCashFlowData.cash_flow.net_change}"\n`;

            // Download CSV
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', `cash_flow_${currentCashFlowData.start_date}_to_${currentCashFlowData.end_date}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            showAlert('Cash flow statement exported successfully', 'success');
        }

        // Export all reports at once
        function exportAllReports() {
            const format = 'csv';
            let exportCount = 0;

            // Export Income Statement if available
            if (currentIncomeStatementData) {
                exportIncomeStatement(format);
                exportCount++;
            }

            // Export Balance Sheet if available
            if (currentBalanceSheetData) {
                setTimeout(() => exportBalanceSheet(format), 500);
                exportCount++;
            }

            // Export Cash Flow if available
            if (currentCashFlowData) {
                setTimeout(() => exportCashFlow(format), 1000);
                exportCount++;
            }

            if (exportCount === 0) {
                showAlert('Please generate at least one report first', 'warning');
            } else {
                showAlert(`Exporting ${exportCount} report(s) as CSV files...`, 'success');
            }
        }

        // Export current active tab report as PDF (actually CSV)
        function exportCurrentReportPDF() {
            const activeTab = document.querySelector('.nav-link.active');
            if (!activeTab) {
                showAlert('No active report tab found', 'warning');
                return;
            }

            const tabId = activeTab.getAttribute('id');

            switch(tabId) {
                case 'income-tab':
                    exportIncomeStatement('csv');
                    break;
                case 'balance-tab':
                    exportBalanceSheet('csv');
                    break;
                case 'cashflow-tab':
                    exportCashFlow('csv');
                    break;
                default:
                    showAlert('Please switch to a report tab (Income, Balance Sheet, or Cash Flow)', 'info');
            }
        }

        // Export current active tab report as Excel (CSV format)
        function exportCurrentReportExcel() {
            const activeTab = document.querySelector('.nav-link.active');
            if (!activeTab) {
                showAlert('No active report tab found', 'warning');
                return;
            }

            const tabId = activeTab.getAttribute('id');

            switch(tabId) {
                case 'income-tab':
                    if (!currentIncomeStatementData) {
                        showAlert('Please generate the Income Statement first', 'warning');
                        return;
                    }
                    exportIncomeStatement('csv');
                    break;
                case 'balance-tab':
                    if (!currentBalanceSheetData) {
                        showAlert('Please generate the Balance Sheet first', 'warning');
                        return;
                    }
                    exportBalanceSheet('csv');
                    break;
                case 'cashflow-tab':
                    if (!currentCashFlowData) {
                        showAlert('Please generate the Cash Flow Statement first', 'warning');
                        return;
                    }
                    exportCashFlow('csv');
                    break;
                default:
                    showAlert('Please switch to a report tab (Income, Balance Sheet, or Cash Flow)', 'info');
            }
        }

        // Print current active report
        function printCurrentReport() {
            const activeTab = document.querySelector('.nav-link.active');
            if (!activeTab) {
                showAlert('No active report tab found', 'warning');
                return;
            }

            const tabId = activeTab.getAttribute('id');
            let printContainer;

            switch(tabId) {
                case 'income-tab':
                    printContainer = document.getElementById('incomeStatementContainer');
                    if (!currentIncomeStatementData) {
                        showAlert('Please generate the Income Statement first', 'warning');
                        return;
                    }
                    break;
                case 'balance-tab':
                    printContainer = document.getElementById('balanceSheetContainer');
                    if (!currentBalanceSheetData) {
                        showAlert('Please generate the Balance Sheet first', 'warning');
                        return;
                    }
                    break;
                case 'cashflow-tab':
                    printContainer = document.getElementById('cashFlowContainer');
                    if (!currentCashFlowData) {
                        showAlert('Please generate the Cash Flow Statement first', 'warning');
                        return;
                    }
                    break;
                default:
                    showAlert('Please switch to a report tab (Income, Balance Sheet, or Cash Flow)', 'info');
                    return;
            }

            // Open print dialog with the report content
            const printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write('<html><head><title>Print Report</title>');
            printWindow.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">');
            printWindow.document.write('<style>');
            printWindow.document.write(`
                body { font-family: Arial, sans-serif; padding: 20px; }
                .financial-statement { max-width: 800px; margin: 0 auto; }
                .statement-title { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
                .statement-period { font-size: 14px; color: #666; margin-bottom: 20px; }
                .account-category { margin-bottom: 20px; }
                .account-category h6 { font-weight: bold; border-bottom: 2px solid #000; padding-bottom: 5px; }
                .account-item { display: flex; justify-content: space-between; padding: 5px 0; }
                .total-row { font-weight: bold; border-top: 1px solid #000; margin-top: 5px; padding-top: 5px; }
                @media print {
                    .no-print { display: none; }
                }
            `);
            printWindow.document.write('</style></head><body>');
            printWindow.document.write(printContainer.innerHTML);
            printWindow.document.write('<script src="../includes/tab_persistence.js?v=1"><\/script>');
            printWindow.document.write('</body></html>');
            printWindow.document.close();

            setTimeout(() => {
                printWindow.print();
            }, 250);

            showAlert('Opening print dialog...', 'info');
        }

        // Toggle expense detail breakdown in cash flow statement
        function toggleExpenseDetail(categoryId) {
            const detailDiv = document.getElementById(`detail-${categoryId}`);
            const icon = document.getElementById(`icon-${categoryId}`);

            if (detailDiv && icon) {
                if (detailDiv.classList.contains('d-none')) {
                    detailDiv.classList.remove('d-none');
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                } else {
                    detailDiv.classList.add('d-none');
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                }
            }
        }

        // Email current report
        function emailCurrentReport() {
            const activeTab = document.querySelector('.nav-link.active');
            if (!activeTab) {
                showAlert('No active report tab found', 'warning');
                return;
            }

            const tabId = activeTab.getAttribute('id');
            let reportName = '';
            let hasData = false;

            switch(tabId) {
                case 'income-tab':
                    reportName = 'Income Statement';
                    hasData = !!currentIncomeStatementData;
                    break;
                case 'balance-tab':
                    reportName = 'Balance Sheet';
                    hasData = !!currentBalanceSheetData;
                    break;
                case 'cashflow-tab':
                    reportName = 'Cash Flow Statement';
                    hasData = !!currentCashFlowData;
                    break;
                default:
                    showAlert('Please switch to a report tab (Income, Balance Sheet, or Cash Flow)', 'info');
                    return;
            }

            if (!hasData) {
                showAlert(`Please generate the ${reportName} first`, 'warning');
                return;
            }

            // Prompt for email address
            const email = prompt(`Enter email address to send ${reportName}:`);

            if (email && email.trim()) {
                // Validate email format
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    showAlert('Please enter a valid email address', 'warning');
                    return;
                }

                // Show loading indicator
                const loadingAlert = showAlert('Sending email...', 'info');

                // Get current date range from filters
                const dateFrom = document.getElementById('reportDateFrom')?.value || '';
                const dateTo = document.getElementById('reportDateTo')?.value || '';

                // Send email via API
                fetch('../api/reports.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'email',
                        report_type: tabId.replace('-tab', ''),
                        report_name: reportName,
                        email: email,
                        date_from: dateFrom,
                        date_to: dateTo
                    })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        showAlert(`Report successfully sent to ${email}!`, 'success');
                    } else {
                        throw new Error(result.error || 'Failed to send email');
                    }
                })
                .catch(error => {
                    showAlert('Error sending email: ' + error.message, 'danger');
                });
            }
        }

        // Show alert function
        function showAlert(message, type = 'info') {
            // Create alert element
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = `
                top: 20px;
                right: 20px;
                z-index: 9999;
                max-width: 400px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            `;

            const icons = {
                success: 'fas fa-check-circle',
                danger: 'fas fa-exclamation-triangle',
                warning: 'fas fa-exclamation-triangle',
                info: 'fas fa-info-circle'
            };

            alertDiv.innerHTML = `
                <i class="${icons[type] || 'fas fa-info-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.body.appendChild(alertDiv);

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        let reportTrendsChart = null;

        // View charts functionality
        function viewCharts() {
            const analyticsTab = document.getElementById('analytics-tab');
            if (analyticsTab) {
                analyticsTab.click();
            }
            loadAnalyticsSummary();
        }

        function loadAnalyticsSummary() {
            fetch('../api/reports.php?type=analytics_summary')
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        showAlert(data.error || 'Failed to load analytics', 'danger');
                        return;
                    }

                    const mtdRevenue = data.mtd.revenue || 0;
                    const mtdExpenses = data.mtd.expenses || 0;
                    const mtdNet = data.mtd.net || 0;

                    document.getElementById('analyticsMtdRevenue').textContent = 'ƒ,ñ' + mtdRevenue.toLocaleString();
                    document.getElementById('analyticsMtdExpenses').textContent = 'ƒ,ñ' + mtdExpenses.toLocaleString();
                    document.getElementById('analyticsNetResult').textContent = 'ƒ,ñ' + mtdNet.toLocaleString();
                    document.getElementById('analyticsNetResult').className = 'fw-bold fs-4 ' + (mtdNet >= 0 ? 'text-success' : 'text-danger');

                    const ctx = document.getElementById('reportTrendsChart');
                    if (!ctx) return;

                    if (reportTrendsChart) {
                        reportTrendsChart.destroy();
                    }

                    reportTrendsChart = new Chart(ctx.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: data.trend.labels,
                            datasets: [
                                {
                                    label: 'Revenue',
                                    data: data.trend.revenue,
                                    borderColor: 'rgba(40, 167, 69, 1)',
                                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                    tension: 0.3,
                                    fill: true
                                },
                                {
                                    label: 'Expenses',
                                    data: data.trend.expenses,
                                    borderColor: 'rgba(220, 53, 69, 1)',
                                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                                    tension: 0.3,
                                    fill: true
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'top'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return 'ƒ,ñ' + value.toLocaleString();
                                        }
                                    }
                                }
                            }
                        }
                    });
                })
                .catch(error => showAlert('Error: ' + error.message, 'danger'));
        }

        document.addEventListener('DOMContentLoaded', function() {
            const analyticsTab = document.getElementById('analytics-tab');
            if (analyticsTab) {
                analyticsTab.addEventListener('shown.bs.tab', loadAnalyticsSummary);
            }
        });
    