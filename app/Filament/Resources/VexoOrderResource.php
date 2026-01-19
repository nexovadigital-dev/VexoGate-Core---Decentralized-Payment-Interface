<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VexoOrderResource\Pages;
use App\Models\VexoOrder;
use App\Services\PolygonService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class VexoOrderResource extends Resource
{
    protected static ?string $model = VexoOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'VexoGate Orders';

    protected static ?string $modelLabel = 'Order';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Order Information')
                    ->schema([
                        Forms\Components\TextInput::make('id')
                            ->label('Order ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('domain_origin')
                            ->label('Merchant Domain')
                            ->disabled(),
                        Forms\Components\TextInput::make('merchant_order_id')
                            ->label('Merchant Order ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('client_email')
                            ->label('Client Email')
                            ->email()
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Financial Details')
                    ->schema([
                        Forms\Components\TextInput::make('fiat_amount')
                            ->label('Fiat Amount')
                            ->prefix('$')
                            ->disabled(),
                        Forms\Components\TextInput::make('fiat_currency')
                            ->label('Currency')
                            ->disabled(),
                        Forms\Components\TextInput::make('crypto_received')
                            ->label('USDC Received')
                            ->suffix('USDC')
                            ->disabled(),
                        Forms\Components\TextInput::make('vexo_fee')
                            ->label('VexoGate Fee')
                            ->prefix('$')
                            ->disabled(),
                        Forms\Components\TextInput::make('gas_cost_matic')
                            ->label('Gas Cost')
                            ->suffix('MATIC')
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make('Blockchain Addresses')
                    ->schema([
                        Forms\Components\TextInput::make('temp_wallet_address')
                            ->label('Temporary Wallet')
                            ->copyable()
                            ->disabled(),
                        Forms\Components\TextInput::make('merchant_dest_wallet')
                            ->label('Merchant Wallet')
                            ->copyable()
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Transaction Hashes')
                    ->schema([
                        Forms\Components\TextInput::make('txid_in')
                            ->label('Incoming TX')
                            ->copyable()
                            ->disabled()
                            ->suffix(fn ($state) => $state ? '🔗' : ''),
                        Forms\Components\TextInput::make('txid_gas')
                            ->label('Gas Injection TX')
                            ->copyable()
                            ->disabled()
                            ->suffix(fn ($state) => $state ? '🔗' : ''),
                        Forms\Components\TextInput::make('txid_out_merchant')
                            ->label('Merchant Payout TX')
                            ->copyable()
                            ->disabled()
                            ->suffix(fn ($state) => $state ? '🔗' : ''),
                        Forms\Components\TextInput::make('txid_out_fee')
                            ->label('Fee Collection TX')
                            ->copyable()
                            ->disabled()
                            ->suffix(fn ($state) => $state ? '🔗' : ''),
                    ])->columns(2),

                Forms\Components\Section::make('Status & Control')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Current Status')
                            ->options([
                                'waiting_payment' => 'Waiting Payment',
                                'funds_detected' => 'Funds Detected',
                                'gas_injected' => 'Gas Injected',
                                'distributing' => 'Distributing',
                                'completed' => 'Completed',
                                'manual_review' => 'Manual Review',
                                'refunded' => 'Refunded',
                            ])
                            ->disabled(),
                        Forms\Components\Toggle::make('manual_override')
                            ->label('Manual Override Enabled')
                            ->helperText('Allows processing even if manual approval is required')
                            ->disabled(),
                        Forms\Components\Textarea::make('last_error_log')
                            ->label('Error Log')
                            ->rows(3)
                            ->disabled()
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'secondary' => 'waiting_payment',
                        'info' => 'funds_detected',
                        'warning' => 'gas_injected',
                        'primary' => 'distributing',
                        'success' => 'completed',
                        'danger' => 'manual_review',
                        'gray' => 'refunded',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('domain_origin')
                    ->label('Merchant')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('fiat_amount')
                    ->label('Amount')
                    ->money(fn ($record) => $record->fiat_currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('crypto_received')
                    ->label('USDC')
                    ->suffix(' USDC')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('temp_wallet_address')
                    ->label('Temp Wallet')
                    ->copyable()
                    ->limit(10)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('txid_out_merchant')
                    ->label('TX Hash')
                    ->copyable()
                    ->limit(10)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('manual_override')
                    ->label('Override')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M d, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'waiting_payment' => 'Waiting Payment',
                        'funds_detected' => 'Funds Detected',
                        'gas_injected' => 'Gas Injected',
                        'distributing' => 'Distributing',
                        'completed' => 'Completed',
                        'manual_review' => 'Manual Review',
                        'refunded' => 'Refunded',
                    ]),
                Tables\Filters\Filter::make('manual_review_only')
                    ->label('Manual Review Only')
                    ->query(fn ($query) => $query->where('status', 'manual_review')),
                Tables\Filters\Filter::make('high_value')
                    ->label('High Value (>$500)')
                    ->query(fn ($query) => $query->where('fiat_amount', '>', 500)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('polygonscan')
                    ->label('PolygonScan')
                    ->icon('heroicon-o-globe-alt')
                    ->color('info')
                    ->url(function (VexoOrder $record) {
                        if (!$record->txid_out_merchant) {
                            return null;
                        }
                        return $record->getPolygonScanUrl($record->txid_out_merchant);
                    }, shouldOpenInNewTab: true)
                    ->visible(fn (VexoOrder $record) => $record->txid_out_merchant !== null),

                // ACCIÓN 1: FORZAR APROBACIÓN
                Tables\Actions\Action::make('force_approve')
                    ->label('Force Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Force Approve Order')
                    ->modalDescription('This will override manual review and allow the system to process the order automatically.')
                    ->visible(fn (VexoOrder $record) => $record->status === 'manual_review')
                    ->action(function (VexoOrder $record) {
                        $record->manual_override = true;
                        $record->updateStatus('gas_injected', 'Manually approved by admin');
                        $record->save();

                        Notification::make()
                            ->success()
                            ->title('Order Approved')
                            ->body("Order #{$record->id} has been approved and will be processed automatically.")
                            ->send();
                    }),

                // ACCIÓN 2: REEMBOLSO / DESVÍO
                Tables\Actions\Action::make('manual_send')
                    ->label('Manual Send')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('danger')
                    ->form([
                        Forms\Components\TextInput::make('custom_wallet')
                            ->label('Destination Wallet Address')
                            ->required()
                            ->placeholder('0x...')
                            ->helperText('Enter the wallet address where you want to send the funds'),
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount (USDC)')
                            ->numeric()
                            ->required()
                            ->helperText(fn (VexoOrder $record) => "Available: {$record->crypto_received} USDC"),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Manual Fund Transfer')
                    ->modalDescription('⚠️ This will send USDC from the temporary wallet to a custom address. Use for refunds or emergency recovery.')
                    ->visible(fn (VexoOrder $record) => $record->crypto_received > 0 && !$record->isFinal())
                    ->action(function (VexoOrder $record, array $data) {
                        try {
                            $polygon = app(PolygonService::class);
                            $txHash = $polygon->sendUsdc(
                                $record->temp_private_key,
                                $data['custom_wallet'],
                                (float) $data['amount']
                            );

                            $record->updateStatus('refunded', "Manual send to {$data['custom_wallet']} - TX: {$txHash}");

                            Notification::make()
                                ->success()
                                ->title('Funds Sent')
                                ->body("Successfully sent {$data['amount']} USDC. TX: {$txHash}")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Transfer Failed')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                // ACCIÓN 3: RESCATAR COMISIÓN
                Tables\Actions\Action::make('rescue_fee')
                    ->label('Rescue Fee')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Rescue VexoGate Fee')
                    ->modalDescription('This will attempt to send the VexoGate fee to our wallet if it failed during automatic processing.')
                    ->visible(fn (VexoOrder $record) => $record->status === 'completed' && !$record->txid_out_fee && $record->vexo_fee > 0)
                    ->action(function (VexoOrder $record) {
                        try {
                            $polygon = app(PolygonService::class);
                            $vexoWallet = config('vexogate.vexo_wallet_address');

                            $txHash = $polygon->sendUsdc(
                                $record->temp_private_key,
                                $vexoWallet,
                                $record->vexo_fee
                            );

                            $record->txid_out_fee = $txHash;
                            $record->save();

                            Notification::make()
                                ->success()
                                ->title('Fee Rescued')
                                ->body("Successfully collected {$record->vexo_fee} USDC. TX: {$txHash}")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Rescue Failed')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVexoOrders::route('/'),
            'view' => Pages\ViewVexoOrder::route('/{record}'),
        ];
    }
}
