# Contribuindo — perfex-module-testkit

## Fluxo

1. **Issue** aberta no GitHub descrevendo o que muda e os critérios de validação.
2. **Branch** `feature/<slug>` a partir de `develop` atualizado:
   ```bash
   git checkout develop && git pull origin develop
   git checkout -b feature/<slug>
   ```
3. **Desenvolvimento** com autoteste: `composer install && composer test` precisa passar localmente.
4. **Documentação**: `CHANGELOG.md` (e `README.md` quando a API pública mudar).
5. **PR contra `develop`**, squash (o título vira o commit — manter `Refs #N`):
   ```bash
   git push -u origin feature/<slug>
   gh pr create --base develop --title "feat: descrição Refs #N" --body "Refs #N"
   gh pr merge --squash --delete-branch
   ```
   **PR com verificação do CI vermelha não é mesclado.**
6. **Release**: PR `develop` → `main` com merge normal, tag imutável + tag móvel `v0`:
   ```bash
   gh pr create --base main --head develop --title "release: v0.x.y" --body "Closes #N"
   gh pr merge --merge
   git checkout main && git pull origin main
   git tag -a v0.x.y -m "perfex-module-testkit v0.x.y"
   git tag -f v0
   git push origin v0.x.y && git push -f origin v0
   ```

## Compatibilidade

- Mudança incompatível em stub ou API do kit sobe o minor (`0.x`) e é descrita no CHANGELOG com o
  que os módulos precisam ajustar.
- O kit precisa passar no CI em PHP 8.1 a 8.5.
