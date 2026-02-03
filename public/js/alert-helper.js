/**
 * Standardized Alert Helper for Warehouse System
 * Provides consistent SweetAlert2 styling across all pages
 */

const WarehouseAlert = {
    /**
     * Confirmation alert with warning style
     */
    confirm: function ({
        title = "Konfirmasi",
        message = "",
        warningItems = [],
        infoText = "",
        confirmText = "Ya, Lanjutkan",
        cancelText = "Batal",
        confirmColor = "#10B981",
        onConfirm = null,
    }) {
        const warningHtml =
            warningItems.length > 0
                ? `
            <div style="background-color: #FEF3C7; padding: 16px; border-radius: 8px; border-left: 4px solid #F59E0B; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <div style="display: flex; align-items: flex-start; gap: 10px;">
                    <i class="bi bi-exclamation-triangle" style="color: #F59E0B; font-size: 1.3rem; margin-top: 2px; flex-shrink: 0;"></i>
                    <div style="flex: 1;">
                        <strong style="color: #92400E; font-size: 1rem; display: block; margin-bottom: 8px;">Perhatian:</strong>
                        <ul style="margin: 0; padding-left: 20px; color: #78350F; line-height: 1.7; list-style-type: disc;">
                            ${warningItems.map((item) => `<li style="color: #78350F;">${item}</li>`).join("")}
                        </ul>
                    </div>
                </div>
            </div>
        `
                : "";

        const infoHtml = infoText
            ? `
            <div style="display: flex; align-items: center; gap: 8px; padding: 12px; background-color: #F3F4F6; border-radius: 6px; margin-top: 20px;">
                <i class="bi bi-info-circle" style="color: #6B7280; font-size: 1.1rem;"></i>
                <span style="color: #4B5563; font-size: 0.95rem;">${infoText}</span>
            </div>
        `
            : "";

        Swal.fire({
            title: `<strong style="font-size: 1.5rem; color: #374151;">${title}</strong>`,
            html: `
                <div style="text-align: left; padding: 15px 20px;">
                    <p style="margin-bottom: ${warningItems.length > 0 ? "0" : "20px"}; font-size: 1rem; color: #4b5563;">
                        ${message}
                    </p>
                    ${warningHtml}
                    ${infoHtml}
                </div>
            `,
            icon: "warning",
            iconColor: "#F59E0B",
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: cancelText,
            confirmButtonColor: confirmColor,
            cancelButtonColor: "#6B7280",
            reverseButtons: true,
            width: "600px",
            buttonsStyling: true,
        }).then((result) => {
            if (result.isConfirmed && onConfirm) {
                onConfirm();
            }
        });
    },

    /**
     * Delete confirmation alert with danger style
     */
    delete: function ({
        title = "Hapus Data?",
        itemName = "",
        warningItems = [],
        confirmText = "Ya, Hapus",
        cancelText = "Batal",
        onConfirm = null,
    }) {
        const warningHtml =
            warningItems.length > 0
                ? `
            <div style="background-color: #FEE2E2; padding: 16px; border-radius: 8px; border-left: 4px solid #DC2626; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <div style="display: flex; align-items: flex-start; gap: 10px;">
                    <i class="bi bi-exclamation-triangle" style="color: #DC2626; font-size: 1.3rem; margin-top: 2px; flex-shrink: 0;"></i>
                    <div style="flex: 1;">
                        <strong style="color: #991B1B; font-size: 1rem; display: block; margin-bottom: 8px;">Perhatian:</strong>
                        <ul style="margin: 0; padding-left: 20px; color: #7F1D1D; line-height: 1.7; list-style-type: disc;">
                            ${warningItems.map((item) => `<li style="color: #7F1D1D;">${item}</li>`).join("")}
                        </ul>
                    </div>
                </div>
            </div>
        `
                : "";

        Swal.fire({
            title: `<strong style="font-size: 1.5rem; color: #374151;">${title}</strong>`,
            html: `
                <div style="text-align: left; padding: 15px 20px;">
                    <p style="margin-bottom: ${warningItems.length > 0 ? "0" : "20px"}; font-size: 1rem; color: #4b5563;">
                        Anda akan menghapus <strong style="color: #DC2626;">${itemName}</strong> dari sistem.
                    </p>
                    ${warningHtml}
                </div>
            `,
            icon: "error",
            iconColor: "#DC2626",
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: cancelText,
            confirmButtonColor: "#DC2626",
            cancelButtonColor: "#6B7280",
            reverseButtons: true,
            width: "600px",
            buttonsStyling: true,
        }).then((result) => {
            if (result.isConfirmed && onConfirm) {
                onConfirm();
            }
        });
    },

    /**
     * Info confirmation alert with blue style
     */
    info: function ({
        title = "Konfirmasi",
        message = "",
        details = {},
        infoText = "",
        confirmText = "Ya, Lanjutkan",
        cancelText = "Batal",
        onConfirm = null,
    }) {
        const detailsHtml =
            Object.keys(details).length > 0
                ? `
            <div style="background-color: #EFF6FF; padding: 16px; border-radius: 8px; border-left: 4px solid #3B82F6; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <div style="display: flex; align-items: flex-start; gap: 10px;">
                    <i class="bi bi-info-circle" style="color: #3B82F6; font-size: 1.3rem; margin-top: 2px; flex-shrink: 0;"></i>
                    <div style="flex: 1;">
                        <strong style="color: #1E40AF; font-size: 1rem; display: block; margin-bottom: 8px;">Detail:</strong>
                        <ul style="margin: 0; padding-left: 20px; color: #1E3A8A; line-height: 1.7; list-style-type: none;">
                            ${Object.entries(details)
                                .map(
                                    ([key, value]) =>
                                        `<li style="color: #1E3A8A; margin-bottom: 4px;"><strong>${key}:</strong> ${value}</li>`,
                                )
                                .join("")}
                        </ul>
                    </div>
                </div>
            </div>
        `
                : "";

        const infoHtml = infoText
            ? `
            <div style="display: flex; align-items: center; gap: 8px; padding: 12px; background-color: #F3F4F6; border-radius: 6px; margin-top: 20px;">
                <i class="bi bi-lightbulb" style="color: #6B7280; font-size: 1.1rem;"></i>
                <span style="color: #4B5563; font-size: 0.95rem;">${infoText}</span>
            </div>
        `
            : "";

        Swal.fire({
            title: `<strong style="font-size: 1.5rem; color: #374151;">${title}</strong>`,
            html: `
                <div style="text-align: left; padding: 15px 20px;">
                    <p style="margin-bottom: ${Object.keys(details).length > 0 ? "0" : "20px"}; font-size: 1rem; color: #4b5563;">
                        ${message}
                    </p>
                    ${detailsHtml}
                    ${infoHtml}
                </div>
            `,
            icon: "info",
            iconColor: "#3B82F6",
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: cancelText,
            confirmButtonColor: "#3B82F6",
            cancelButtonColor: "#6B7280",
            reverseButtons: true,
            width: "600px",
            buttonsStyling: true,
        }).then((result) => {
            if (result.isConfirmed && onConfirm) {
                onConfirm();
            }
        });
    },

    /**
     * Error alert
     */
    error: function ({
        title = "Error",
        message = "Terjadi kesalahan",
        confirmText = "OK",
    }) {
        Swal.fire({
            title: `<strong style="font-size: 1.5rem; color: #374151;">${title}</strong>`,
            html: `
                <div style="text-align: left; padding: 15px 20px;">
                    <p style="margin: 0; font-size: 1rem; color: #4b5563;">
                        ${message}
                    </p>
                </div>
            `,
            icon: "error",
            iconColor: "#DC2626",
            confirmButtonText: confirmText,
            confirmButtonColor: "#DC2626",
            buttonsStyling: true,
        });
    },

    /**
     * Success alert
     */
    success: function ({
        title = "Berhasil",
        message = "Operasi berhasil dilakukan",
        confirmText = "OK",
        onConfirm = null,
    }) {
        Swal.fire({
            title: `<strong style="font-size: 1.5rem; color: #374151;">${title}</strong>`,
            html: `
                <div style="text-align: left; padding: 15px 20px;">
                    <p style="margin: 0; font-size: 1rem; color: #4b5563;">
                        ${message}
                    </p>
                </div>
            `,
            icon: "success",
            iconColor: "#10B981",
            confirmButtonText: confirmText,
            confirmButtonColor: "#10B981",
            buttonsStyling: true,
        }).then((result) => {
            if (result.isConfirmed && onConfirm) {
                onConfirm();
            }
        });
    },
};
