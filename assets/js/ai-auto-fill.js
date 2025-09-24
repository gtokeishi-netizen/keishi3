/**
 * Grant Insight Perfect AI自動入力機能 - JavaScript
 * 
 * @package Grant_Insight_Perfect
 * @version 1.0.0
 * @since 2024.12
 */

(function($) {
    'use strict';

    /**
     * AI自動入力クラス
     */
    class GI_AI_AutoFill {
        constructor() {
            this.isProcessing = false;
            this.currentBatchIndex = 0;
            this.batchTotal = 0;
            this.batchResults = {};
            this.progressInterval = null;
            this.previewData = {};
            
            this.init();
        }

        /**
         * 初期化
         */
        init() {
            this.bindEvents();
            this.initializeUI();
            this.checkDailyLimit();
        }

        /**
         * イベントハンドラーのバインド
         */
        bindEvents() {
            // メイン機能のイベント
            $(document).on('click', '#gi-ai-execute-btn', this.handleExecuteClick.bind(this));
            $(document).on('click', '#gi-ai-rollback-btn', this.handleRollbackClick.bind(this));
            $(document).on('click', '#gi-ai-cancel-btn', this.handleCancelClick.bind(this));

            // フィールド選択のイベント
            $(document).on('click', '#gi-ai-select-all', this.selectAllFields.bind(this));
            $(document).on('click', '#gi-ai-select-none', this.selectNoFields.bind(this));
            $(document).on('click', '#gi-ai-select-high', this.selectHighPriorityFields.bind(this));

            // プレビュー関連のイベント
            $(document).on('click', '#gi-ai-apply-all-btn', this.applyAllPreview.bind(this));
            $(document).on('click', '#gi-ai-cancel-preview-btn', this.cancelPreview.bind(this));
            $(document).on('click', '.gi-ai-apply-field-btn', this.applySingleField.bind(this));
            $(document).on('click', '.gi-ai-edit-field-btn', this.editFieldContent.bind(this));
            $(document).on('click', '.gi-ai-reject-field-btn', this.rejectField.bind(this));

            // バッチ処理のイベント
            $(document).on('click', '#gi-ai-batch-execute-btn', this.handleBatchExecute.bind(this));
            $(document).on('click', '#gi-ai-batch-pause-btn', this.handleBatchPause.bind(this));
            $(document).on('click', '#gi-ai-batch-cancel-btn', this.handleBatchCancel.bind(this));

            // バッチ処理の選択関連
            $(document).on('click', '#gi-ai-batch-select-all', this.selectAllBatchPosts.bind(this));
            $(document).on('click', '#gi-ai-batch-select-none', this.selectNoBatchPosts.bind(this));
            $(document).on('change', '.gi-ai-batch-post-checkbox', this.updateBatchSelectionCount.bind(this));
            $(document).on('change', '.gi-ai-priority-toggle', this.togglePriorityFields.bind(this));

            // フィールド状態の監視
            $(document).on('change', '.gi-ai-field-checkbox', this.updateExecuteButtonState.bind(this));
            $(document).on('change', '#gi-ai-preview-mode', this.togglePreviewMode.bind(this));

            // ページ離脱時の警告
            $(window).on('beforeunload', this.handlePageUnload.bind(this));

            // リアルタイム進捗更新
            $(document).on('gi-ai-progress-update', this.handleProgressUpdate.bind(this));
        }

        /**
         * UI初期化
         */
        initializeUI() {
            this.updateExecuteButtonState();
            this.initializeTooltips();
            this.initializeProgressBars();
            this.setupFieldValidation();
        }

        /**
         * 日次制限チェック
         */
        checkDailyLimit() {
            if (typeof gi_ai_ajax !== 'undefined') {
                const usage = gi_ai_ajax.daily_usage || 0;
                const limit = gi_ai_ajax.daily_limit || 100;
                
                if (usage >= limit) {
                    this.showLimitReachedWarning();
                    $('#gi-ai-execute-btn').prop('disabled', true);
                }
            }
        }

        /**
         * 制限到達警告の表示
         */
        showLimitReachedWarning() {
            this.showNotification(
                gi_ai_ajax.strings.daily_limit_reached || '本日の利用上限に達しました',
                'error'
            );
        }

        /**
         * 実行ボタンクリック処理
         */
        handleExecuteClick(e) {
            e.preventDefault();

            if (this.isProcessing) {
                return;
            }

            const selectedFields = this.getSelectedFields();
            
            if (selectedFields.length === 0) {
                this.showNotification(
                    gi_ai_ajax.strings.no_fields_selected || '処理対象のフィールドを選択してください',
                    'warning'
                );
                return;
            }

            if (!this.confirmExecution(selectedFields)) {
                return;
            }

            this.executeAIProcess(selectedFields);
        }

        /**
         * 選択フィールドの取得
         */
        getSelectedFields() {
            const fields = [];
            $('.gi-ai-field-checkbox:checked').each(function() {
                fields.push($(this).val());
            });
            return fields;
        }

        /**
         * 実行確認
         */
        confirmExecution(fields) {
            const message = (gi_ai_ajax.strings.confirm_process || 'AI自動入力を実行しますか？') +
                          `\n\n対象フィールド: ${fields.length}件` +
                          `\n- ${fields.join('\n- ')}`;
            
            return confirm(message);
        }

        /**
         * AI処理実行
         */
        executeAIProcess(fields) {
            this.isProcessing = true;
            this.showProgress(true);
            
            const postId = this.getPostId();
            const skipFilled = $('#gi-ai-skip-filled').is(':checked');
            const previewMode = $('#gi-ai-preview-mode').is(':checked');

            const data = {
                action: 'gi_ai_auto_fill',
                nonce: gi_ai_ajax.nonce,
                post_id: postId,
                target_fields: fields,
                skip_filled: skipFilled,
                preview_mode: previewMode
            };

            this.updateProgress(0, 'AI処理を開始しています...');

            $.ajax({
                url: gi_ai_ajax.ajax_url,
                type: 'POST',
                data: data,
                timeout: 120000, // 2分タイムアウト
                success: this.handleExecuteSuccess.bind(this),
                error: this.handleExecuteError.bind(this),
                complete: this.handleExecuteComplete.bind(this)
            });
        }

        /**
         * 実行成功処理
         */
        handleExecuteSuccess(response) {
            if (response.success) {
                const data = response.data;
                
                this.showNotification(
                    `処理が完了しました。${Object.keys(data.updated_fields || {}).length}件のフィールドを更新しました。`,
                    'success'
                );

                if ($('#gi-ai-preview-mode').is(':checked')) {
                    this.showPreview(data.updated_fields);
                } else {
                    this.handleDirectApply(data);
                }

                // 使用量の更新
                this.updateUsageDisplay(data.tokens_used);
                
            } else {
                this.showNotification(
                    'エラー: ' + (response.data || '不明なエラーが発生しました'),
                    'error'
                );
            }
        }

        /**
         * 実行エラー処理
         */
        handleExecuteError(xhr, status, error) {
            let errorMessage = 'エラー: ';
            
            if (status === 'timeout') {
                errorMessage += 'タイムアウトしました。処理に時間がかかりすぎています。';
            } else if (xhr.responseJSON && xhr.responseJSON.data) {
                errorMessage += xhr.responseJSON.data;
            } else {
                errorMessage += '通信エラーが発生しました。';
            }
            
            this.showNotification(errorMessage, 'error');
        }

        /**
         * 実行完了処理
         */
        handleExecuteComplete() {
            this.isProcessing = false;
            this.showProgress(false);
        }

        /**
         * 直接適用処理
         */
        handleDirectApply(data) {
            // ページリロードして変更を反映
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        }

        /**
         * プレビュー表示
         */
        showPreview(updatedFields) {
            if (!updatedFields || Object.keys(updatedFields).length === 0) {
                this.showNotification('生成されたコンテンツがありません', 'warning');
                return;
            }

            this.previewData = updatedFields;
            
            const data = {
                action: 'gi_ai_preview_content',
                nonce: gi_ai_ajax.nonce,
                post_id: this.getPostId(),
                generated_content: updatedFields
            };

            $.ajax({
                url: gi_ai_ajax.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        $('#gi-ai-preview-content').html(response.data.preview_html);
                        $('#gi-ai-preview-container').removeClass('hidden');
                        
                        // プレビューエリアにスクロール
                        $('html, body').animate({
                            scrollTop: $('#gi-ai-preview-container').offset().top - 50
                        }, 500);
                        
                        this.initializePreviewInteractions();
                    } else {
                        this.showNotification('プレビューの生成に失敗しました', 'error');
                    }
                },
                error: () => {
                    this.showNotification('プレビューの通信エラーが発生しました', 'error');
                }
            });
        }

        /**
         * プレビューインタラクションの初期化
         */
        initializePreviewInteractions() {
            // 編集可能なテキストエリアの設定
            $('.gi-ai-textarea-content').on('input', function() {
                const fieldName = $(this).closest('.gi-ai-generated-content').data('field');
                const charCount = $(this).val().length;
                $(this).siblings('.gi-ai-char-count').text(charCount + ' 文字');
            });

            // プレビューフィールドのアニメーション
            $('.gi-ai-preview-field').hide().fadeIn(300);
        }

        /**
         * 全てのプレビューを適用
         */
        applyAllPreview() {
            if (!this.previewData || Object.keys(this.previewData).length === 0) {
                this.showNotification('適用するデータがありません', 'warning');
                return;
            }

            if (!confirm('すべてのフィールドを適用しますか？')) {
                return;
            }

            this.applyFieldData(this.previewData);
        }

        /**
         * 単一フィールドの適用
         */
        applySingleField(e) {
            const fieldName = $(e.target).data('field');
            const content = this.getFieldContentFromPreview(fieldName);
            
            if (!content) {
                this.showNotification('適用するコンテンツが見つかりません', 'warning');
                return;
            }

            const fieldData = {};
            fieldData[fieldName] = content;
            
            this.applyFieldData(fieldData);
        }

        /**
         * プレビューからフィールドコンテンツを取得
         */
        getFieldContentFromPreview(fieldName) {
            const container = $(`.gi-ai-generated-content[data-field="${fieldName}"]`);
            
            if (container.length === 0) {
                return null;
            }

            const textarea = container.find('.gi-ai-textarea-content');
            if (textarea.length > 0) {
                return textarea.val();
            }

            const wysiwyg = container.find('.gi-ai-wysiwyg-content');
            if (wysiwyg.length > 0) {
                return wysiwyg.html();
            }

            return container.text();
        }

        /**
         * フィールドデータの適用
         */
        applyFieldData(fieldData) {
            const data = {
                action: 'gi_ai_apply_content',
                nonce: gi_ai_ajax.nonce,
                post_id: this.getPostId(),
                field_data: fieldData
            };

            $.ajax({
                url: gi_ai_ajax.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.showNotification(
                            `${response.data.updated_fields.length}件のフィールドを更新しました`,
                            'success'
                        );
                        
                        // 適用されたフィールドをプレビューから削除
                        response.data.updated_fields.forEach(fieldName => {
                            $(`.gi-ai-preview-field:has([data-field="${fieldName}"])`).fadeOut(300);
                        });
                        
                        // しばらくしてページリロード
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        this.showNotification('適用に失敗しました: ' + response.data, 'error');
                    }
                },
                error: () => {
                    this.showNotification('適用の通信エラーが発生しました', 'error');
                }
            });
        }

        /**
         * フィールドコンテンツの編集
         */
        editFieldContent(e) {
            const fieldName = $(e.target).data('field');
            const container = $(`.gi-ai-generated-content[data-field="${fieldName}"]`);
            
            // 編集モードに切り替え
            container.addClass('edit-mode');
            
            const wysiwyg = container.find('.gi-ai-wysiwyg-content');
            if (wysiwyg.length > 0) {
                // WYSIWYGエディターの場合
                this.enableWysiwygEditing(wysiwyg, fieldName);
            } else {
                // テキストエリアの場合
                const textarea = container.find('.gi-ai-textarea-content');
                textarea.prop('readonly', false).focus();
            }

            // 編集完了ボタンの追加
            if (container.find('.gi-ai-edit-complete-btn').length === 0) {
                const completeBtn = $('<button type="button" class="button gi-ai-edit-complete-btn" data-field="' + fieldName + '">編集完了</button>');
                container.append(completeBtn);
                
                completeBtn.on('click', () => {
                    this.completeFieldEdit(fieldName);
                });
            }
        }

        /**
         * WYSIWYG編集の有効化
         */
        enableWysiwygEditing(element, fieldName) {
            element.attr('contenteditable', 'true')
                   .addClass('editable')
                   .focus();
        }

        /**
         * フィールド編集の完了
         */
        completeFieldEdit(fieldName) {
            const container = $(`.gi-ai-generated-content[data-field="${fieldName}"]`);
            
            container.removeClass('edit-mode');
            container.find('.gi-ai-wysiwyg-content').attr('contenteditable', 'false').removeClass('editable');
            container.find('.gi-ai-textarea-content').prop('readonly', true);
            container.find('.gi-ai-edit-complete-btn').remove();
            
            this.showNotification('編集が完了しました', 'success');
        }

        /**
         * フィールドの却下
         */
        rejectField(e) {
            const fieldName = $(e.target).data('field');
            
            if (!confirm(`フィールド「${fieldName}」を却下しますか？`)) {
                return;
            }

            $(e.target).closest('.gi-ai-preview-field').fadeOut(300, function() {
                $(this).remove();
            });

            // プレビューデータからも削除
            if (this.previewData[fieldName]) {
                delete this.previewData[fieldName];
            }
        }

        /**
         * プレビューのキャンセル
         */
        cancelPreview() {
            if (!confirm('プレビューをキャンセルしますか？生成されたコンテンツは破棄されます。')) {
                return;
            }

            $('#gi-ai-preview-container').addClass('hidden');
            $('#gi-ai-preview-content').empty();
            this.previewData = {};
        }

        /**
         * ロールバック処理
         */
        handleRollbackClick(e) {
            e.preventDefault();

            if (!confirm(gi_ai_ajax.strings.confirm_rollback || 'ロールバックを実行しますか？')) {
                return;
            }

            const data = {
                action: 'gi_ai_rollback',
                nonce: gi_ai_ajax.nonce,
                post_id: this.getPostId()
            };

            $.ajax({
                url: gi_ai_ajax.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.showNotification('ロールバックが完了しました', 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        this.showNotification('ロールバックに失敗しました: ' + response.data, 'error');
                    }
                },
                error: () => {
                    this.showNotification('ロールバックの通信エラーが発生しました', 'error');
                }
            });
        }

        /**
         * キャンセル処理
         */
        handleCancelClick(e) {
            e.preventDefault();
            
            if (this.isProcessing) {
                this.isProcessing = false;
                this.showProgress(false);
                this.showNotification('処理をキャンセルしました', 'info');
            }
        }

        /**
         * 全フィールド選択
         */
        selectAllFields() {
            $('.gi-ai-field-checkbox:not(:disabled)').prop('checked', true);
            this.updateExecuteButtonState();
        }

        /**
         * 全フィールド選択解除
         */
        selectNoFields() {
            $('.gi-ai-field-checkbox').prop('checked', false);
            this.updateExecuteButtonState();
        }

        /**
         * 高優先度フィールド選択
         */
        selectHighPriorityFields() {
            $('.gi-ai-field-checkbox').prop('checked', false);
            
            const highPriorityFields = ['ai_summary', 'grant_target', 'eligible_expenses'];
            
            $('.gi-ai-field-checkbox:not(:disabled)').each(function() {
                if (highPriorityFields.includes($(this).val())) {
                    $(this).prop('checked', true);
                }
            });
            
            this.updateExecuteButtonState();
        }

        /**
         * 実行ボタン状態更新
         */
        updateExecuteButtonState() {
            const selectedCount = $('.gi-ai-field-checkbox:checked').length;
            const hasSelection = selectedCount > 0;
            
            $('#gi-ai-execute-btn')
                .prop('disabled', !hasSelection || this.isProcessing)
                .find('span:last-child')
                .text(hasSelection ? `AI自動入力を実行 (${selectedCount}件)` : 'AI自動入力を実行');
        }

        /**
         * 進捗表示の制御
         */
        showProgress(show) {
            if (show) {
                $('#gi-ai-progress').removeClass('hidden');
                $('#gi-ai-execute-btn').prop('disabled', true);
            } else {
                $('#gi-ai-progress').addClass('hidden');
                $('#gi-ai-execute-btn').prop('disabled', false);
                this.updateExecuteButtonState();
            }
        }

        /**
         * 進捗更新
         */
        updateProgress(percentage, message) {
            $('.gi-ai-progress-fill').css('width', percentage + '%');
            $('.gi-ai-progress-text').text(message || '処理中...');
        }

        /**
         * バッチ処理実行
         */
        handleBatchExecute(e) {
            e.preventDefault();

            const selectedPosts = this.getSelectedBatchPosts();
            const selectedFields = this.getSelectedBatchFields();

            if (selectedPosts.length === 0) {
                this.showNotification('処理対象の投稿を選択してください', 'warning');
                return;
            }

            if (selectedFields.length === 0) {
                this.showNotification('処理対象のフィールドを選択してください', 'warning');
                return;
            }

            if (!this.confirmBatchExecution(selectedPosts, selectedFields)) {
                return;
            }

            this.executeBatchProcess(selectedPosts, selectedFields);
        }

        /**
         * バッチ処理対象投稿の取得
         */
        getSelectedBatchPosts() {
            const posts = [];
            $('.gi-ai-batch-post-checkbox:checked').each(function() {
                posts.push(parseInt($(this).val()));
            });
            return posts;
        }

        /**
         * バッチ処理対象フィールドの取得
         */
        getSelectedBatchFields() {
            const fields = [];
            $('.gi-ai-batch-field-checkbox:checked').each(function() {
                fields.push($(this).val());
            });
            return fields;
        }

        /**
         * バッチ実行確認
         */
        confirmBatchExecution(posts, fields) {
            const message = `一括処理を実行しますか？\n\n` +
                          `対象投稿: ${posts.length}件\n` +
                          `対象フィールド: ${fields.length}件\n` +
                          `推定処理時間: ${Math.ceil(posts.length * fields.length * 0.5)}分`;
            
            return confirm(message);
        }

        /**
         * バッチ処理実行
         */
        executeBatchProcess(posts, fields) {
            this.batchTotal = posts.length;
            this.currentBatchIndex = 0;
            this.batchResults = {};

            $('#gi-ai-batch-form').hide();
            $('#gi-ai-batch-progress').removeClass('hidden');

            const options = {
                skip_filled: $('input[name="skip_filled"]').is(':checked'),
                continue_on_error: $('input[name="continue_on_error"]').is(':checked'),
                auto_save: $('input[name="auto_save"]').is(':checked')
            };

            this.processBatchSequentially(posts, fields, options);
        }

        /**
         * バッチの順次処理
         */
        processBatchSequentially(posts, fields, options) {
            if (this.currentBatchIndex >= posts.length || !this.isProcessing) {
                this.completeBatchProcess();
                return;
            }

            const postId = posts[this.currentBatchIndex];
            this.updateBatchProgress(this.currentBatchIndex + 1, this.batchTotal);

            const data = {
                action: 'gi_ai_auto_fill',
                nonce: gi_ai_ajax.nonce,
                post_id: postId,
                target_fields: fields,
                ...options
            };

            $.ajax({
                url: gi_ai_ajax.ajax_url,
                type: 'POST',
                data: data,
                timeout: 180000, // 3分タイムアウト
                success: (response) => {
                    this.batchResults[postId] = {
                        success: response.success,
                        data: response.data || response.error || 'Unknown error'
                    };
                    
                    this.logBatchProgress(postId, response.success, response.data);
                },
                error: (xhr, status, error) => {
                    this.batchResults[postId] = {
                        success: false,
                        data: `通信エラー: ${error}`
                    };
                    
                    this.logBatchProgress(postId, false, error);
                },
                complete: () => {
                    this.currentBatchIndex++;
                    
                    // 次の処理まで間隔をあける
                    setTimeout(() => {
                        this.processBatchSequentially(posts, fields, options);
                    }, 2000);
                }
            });
        }

        /**
         * バッチ進捗の更新
         */
        updateBatchProgress(current, total) {
            const percentage = Math.round((current / total) * 100);
            
            $('.gi-ai-progress-fill').css('width', percentage + '%');
            $('.gi-ai-progress-percentage').text(percentage + '%');
            $('#gi-ai-progress-current').text(current);
            $('#gi-ai-progress-total').text(total);
        }

        /**
         * バッチ進捗ログ
         */
        logBatchProgress(postId, success, data) {
            const logContainer = $('#gi-ai-progress-log-content');
            const timestamp = new Date().toLocaleTimeString();
            const status = success ? '成功' : '失敗';
            const statusClass = success ? 'success' : 'error';
            
            const logEntry = $(`
                <div class="gi-ai-log-entry ${statusClass}">
                    <span class="gi-ai-log-time">${timestamp}</span>
                    <span class="gi-ai-log-post">投稿ID: ${postId}</span>
                    <span class="gi-ai-log-status">${status}</span>
                    <div class="gi-ai-log-details">${JSON.stringify(data)}</div>
                </div>
            `);
            
            logContainer.append(logEntry);
            logContainer.scrollTop(logContainer[0].scrollHeight);
        }

        /**
         * バッチ処理完了
         */
        completeBatchProcess() {
            $('#gi-ai-batch-progress').addClass('hidden');
            $('#gi-ai-batch-results').removeClass('hidden');
            
            const successCount = Object.values(this.batchResults).filter(r => r.success).length;
            const totalCount = Object.keys(this.batchResults).length;
            
            this.showBatchResults(successCount, totalCount);
            this.showNotification(`バッチ処理が完了しました。${successCount}/${totalCount}件が成功しました。`, 'info');
        }

        /**
         * バッチ結果表示
         */
        showBatchResults(successCount, totalCount) {
            const resultsHtml = `
                <div class="gi-ai-batch-summary">
                    <h3>処理結果サマリー</h3>
                    <p>成功: ${successCount}件 / 失敗: ${totalCount - successCount}件 / 合計: ${totalCount}件</p>
                    <div class="gi-ai-result-chart">
                        <div class="gi-ai-success-bar" style="width: ${(successCount/totalCount)*100}%"></div>
                    </div>
                </div>
                
                <div class="gi-ai-batch-details">
                    <h4>詳細結果</h4>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>投稿ID</th>
                                <th>ステータス</th>
                                <th>詳細</th>
                                <th>アクション</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${Object.entries(this.batchResults).map(([postId, result]) => `
                                <tr class="${result.success ? 'success' : 'error'}">
                                    <td>${postId}</td>
                                    <td>${result.success ? '成功' : '失敗'}</td>
                                    <td>${JSON.stringify(result.data)}</td>
                                    <td>
                                        <a href="post.php?post=${postId}&action=edit" class="button button-small">編集</a>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
            
            $('#gi-ai-batch-results-content').html(resultsHtml);
        }

        /**
         * バッチ処理の一時停止
         */
        handleBatchPause() {
            this.isProcessing = false;
            this.showNotification('バッチ処理を一時停止しました', 'info');
        }

        /**
         * バッチ処理のキャンセル
         */
        handleBatchCancel() {
            if (!confirm('バッチ処理をキャンセルしますか？')) {
                return;
            }
            
            this.isProcessing = false;
            $('#gi-ai-batch-progress').addClass('hidden');
            $('#gi-ai-batch-form').show();
            
            this.showNotification('バッチ処理をキャンセルしました', 'info');
        }

        /**
         * バッチ投稿の全選択
         */
        selectAllBatchPosts() {
            $('.gi-ai-batch-post-checkbox').prop('checked', true);
            this.updateBatchSelectionCount();
        }

        /**
         * バッチ投稿の全選択解除
         */
        selectNoBatchPosts() {
            $('.gi-ai-batch-post-checkbox').prop('checked', false);
            this.updateBatchSelectionCount();
        }

        /**
         * バッチ選択数の更新
         */
        updateBatchSelectionCount() {
            const count = $('.gi-ai-batch-post-checkbox:checked').length;
            $('#gi-ai-selected-count').text(count);
        }

        /**
         * 優先度フィールドの切り替え
         */
        togglePriorityFields(e) {
            const priority = $(e.target).data('priority');
            const isChecked = $(e.target).is(':checked');
            
            $(`.gi-ai-batch-field-checkbox.priority-${priority}`).prop('checked', isChecked);
        }

        /**
         * プレビューモードの切り替え
         */
        togglePreviewMode() {
            const isPreviewMode = $('#gi-ai-preview-mode').is(':checked');
            
            if (isPreviewMode) {
                this.showNotification('プレビューモードが有効です。結果は事前確認してから適用されます。', 'info');
            }
        }

        /**
         * 使用量表示の更新
         */
        updateUsageDisplay(tokensUsed) {
            if (typeof gi_ai_ajax !== 'undefined') {
                gi_ai_ajax.daily_usage = (gi_ai_ajax.daily_usage || 0) + 1;
                
                const usage = gi_ai_ajax.daily_usage;
                const limit = gi_ai_ajax.daily_limit;
                const percentage = (usage / limit) * 100;
                
                $('.gi-ai-usage-fill').css('width', Math.min(percentage, 100) + '%');
                $('.gi-ai-usage-text').text(`${usage} / ${limit} 回 (${Math.round(percentage)}%)`);
                
                if (usage >= limit) {
                    this.showLimitReachedWarning();
                    $('#gi-ai-execute-btn').prop('disabled', true);
                }
            }
        }

        /**
         * ツールチップの初期化
         */
        initializeTooltips() {
            $('.gi-ai-field-description').each(function() {
                $(this).attr('title', $(this).text());
            });
            
            // 簡易ツールチップ
            $(document).on('mouseenter', '[title]', function() {
                const $this = $(this);
                const title = $this.attr('title');
                
                if (title) {
                    $this.data('original-title', title).removeAttr('title');
                    
                    const tooltip = $('<div class="gi-ai-tooltip">' + title + '</div>');
                    $('body').append(tooltip);
                    
                    const pos = $this.offset();
                    tooltip.css({
                        top: pos.top + $this.outerHeight() + 5,
                        left: pos.left
                    });
                }
            }).on('mouseleave', '[data-original-title]', function() {
                $(this).attr('title', $(this).data('original-title'));
                $('.gi-ai-tooltip').remove();
            });
        }

        /**
         * プログレスバーの初期化
         */
        initializeProgressBars() {
            $('.gi-ai-usage-bar, .gi-ai-progress-bar').each(function() {
                const $bar = $(this);
                const $fill = $bar.find('.gi-ai-usage-fill, .gi-ai-progress-fill');
                
                // アニメーション効果
                $fill.css('transition', 'width 0.3s ease');
            });
        }

        /**
         * フィールド検証の設定
         */
        setupFieldValidation() {
            // リアルタイムバリデーション
            $('.gi-ai-field-checkbox').on('change', function() {
                const $field = $(this);
                const fieldName = $field.val();
                
                // 依存関係のチェック
                if (fieldName === 'grant_target' && $field.is(':checked')) {
                    $('#ai_summary').prop('checked', true);
                }
            });
        }

        /**
         * 進捗更新ハンドラー
         */
        handleProgressUpdate(event, data) {
            if (data && data.percentage !== undefined) {
                this.updateProgress(data.percentage, data.message);
            }
        }

        /**
         * ページ離脱時の処理
         */
        handlePageUnload(e) {
            if (this.isProcessing) {
                const message = '処理中です。ページを離れますか？';
                e.returnValue = message;
                return message;
            }
        }

        /**
         * 通知表示
         */
        showNotification(message, type = 'info', duration = 5000) {
            const notification = $(`
                <div class="gi-ai-notification gi-ai-notification-${type}">
                    <span class="gi-ai-notification-icon"></span>
                    <span class="gi-ai-notification-message">${message}</span>
                    <button class="gi-ai-notification-close">&times;</button>
                </div>
            `);
            
            // 既存の通知をクリア
            $('.gi-ai-notification').fadeOut(200, function() { $(this).remove(); });
            
            // 新しい通知を表示
            $('body').append(notification);
            notification.fadeIn(300);
            
            // 閉じるボタン
            notification.find('.gi-ai-notification-close').on('click', function() {
                notification.fadeOut(200, function() { $(this).remove(); });
            });
            
            // 自動消去
            if (duration > 0) {
                setTimeout(() => {
                    notification.fadeOut(200, function() { $(this).remove(); });
                }, duration);
            }
        }

        /**
         * 投稿IDの取得
         */
        getPostId() {
            return parseInt($('#post_ID').val()) || 0;
        }

        /**
         * デバッグログ
         */
        debugLog(message, data = null) {
            if (window.console && typeof gi_ai_ajax !== 'undefined' && gi_ai_ajax.debug) {
                console.log('[GI AI] ' + message, data);
            }
        }

        /**
         * ローカルストレージの操作
         */
        saveToStorage(key, data) {
            try {
                localStorage.setItem('gi_ai_' + key, JSON.stringify(data));
            } catch (e) {
                this.debugLog('Failed to save to localStorage', e);
            }
        }

        loadFromStorage(key) {
            try {
                const data = localStorage.getItem('gi_ai_' + key);
                return data ? JSON.parse(data) : null;
            } catch (e) {
                this.debugLog('Failed to load from localStorage', e);
                return null;
            }
        }

        removeFromStorage(key) {
            try {
                localStorage.removeItem('gi_ai_' + key);
            } catch (e) {
                this.debugLog('Failed to remove from localStorage', e);
            }
        }
    }

    /**
     * ユーティリティ関数
     */
    const GI_AI_Utils = {
        /**
         * 文字数カウント（HTML除去）
         */
        countCharacters: function(text) {
            return $('<div>').html(text).text().length;
        },

        /**
         * 時間フォーマット
         */
        formatTime: function(seconds) {
            const minutes = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return minutes > 0 ? `${minutes}分${secs}秒` : `${secs}秒`;
        },

        /**
         * ファイルサイズフォーマット
         */
        formatBytes: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        /**
         * 安全なHTML生成
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * debounce関数
         */
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };

    /**
     * 初期化
     */
    $(document).ready(function() {
        // メインクラスのインスタンス化
        window.GI_AI_AutoFill = new GI_AI_AutoFill();
        
        // ユーティリティをグローバルに
        window.GI_AI_Utils = GI_AI_Utils;
        
        // デバッグ情報
        if (typeof gi_ai_ajax !== 'undefined' && gi_ai_ajax.debug) {
            console.log('[GI AI] JavaScript initialized', {
                version: '1.0.0',
                timestamp: new Date().toISOString()
            });
        }
    });

})(jQuery);